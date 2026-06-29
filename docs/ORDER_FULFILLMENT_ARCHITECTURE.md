# NutraAxis Order Fulfillment Architecture

**Status:** Design Review  
**Last Updated:** 2026-06-15  
**Author:** NutraAxis Engineering

---

## Overview

Orders placed in Adobe Commerce (ACCS) are routed to fulfillment suppliers via an event-driven pipeline built on Azure Service Bus. Each order is normalized into a **Canonical Order Model**, published to a Service Bus Topic, and fan-out to supplier-specific queues via SQL filter subscriptions.

```
ACCS Webhook → Inbound Adapter → SB Topic: orders.canonical → SB Queue: to-{supplier} → Outbound Adapter → Supplier API
```

---

## Supplier Routing Map

| ACCS `fulfillment` Attribute | Supplier Code | Supplier Name  | SB Queue        | Outbound Method |
|------------------------------|---------------|----------------|-----------------|-----------------|
| `CPPC`                       | `nutralogics` | NutraLogics    | `to-nutralogics`| REST API        |
| `Cart`                       | `cartcom`     | Cart.com (Jazz)| `to-cartcom`    | Jazz ASN API    |
| `MTL`                        | `mtl`         | MTL            | `to-mtl`        | REST API (TBD)  |

---

## Canonical Order Model

The canonical model is the **system-of-record representation** of an order inside the pipeline. It is source-agnostic (today: ACCS; future: any storefront). All downstream adapters map FROM this model.

### Top-Level Order

| Field              | Type     | Source (ACCS)                          | Notes                                     |
|--------------------|----------|----------------------------------------|-------------------------------------------|
| `canonicalId`      | string   | generated UUID                         | Internal pipeline ID                      |
| `orderId`          | string   | `increment_id` (e.g. `000000087`)      | Display order number                      |
| `orderEntityId`    | string   | `entity_id`                            | ACCS internal ID                          |
| `source`           | string   | `"accs"`                               | Source system constant                    |
| `environment`      | string   | `ADOBE_COMMERCE_ENVIRONMENT`           | `stage` \| `production`                   |
| `placedAt`         | ISO 8601 | `created_at`                           | When customer placed order                |
| `processedAt`      | ISO 8601 | generated at pipeline entry            | When pipeline received it                 |
| `status`           | string   | `status`                               | `pending` \| `processing` \| `complete`   |
| `currency`         | string   | `order_currency_code`                  | e.g. `USD`                                |
| `subtotal`         | number   | `subtotal`                             |                                           |
| `shippingAmount`   | number   | `shipping_amount`                      |                                           |
| `taxAmount`        | number   | `tax_amount`                           |                                           |
| `discountAmount`   | number   | `discount_amount`                      |                                           |
| `grandTotal`       | number   | `grand_total`                          |                                           |
| `customer`         | object   | see below                              |                                           |
| `shippingAddress`  | object   | see below                              |                                           |
| `billingAddress`   | object   | see below                              |                                           |
| `items`            | array    | see below                              | ALL items across all suppliers            |
| `supplierGroups`   | object   | derived from `items[].supplier`        | Items keyed by supplier code              |

### Customer Object

| Field       | Type   | Source (ACCS)        |
|-------------|--------|----------------------|
| `email`     | string | `customer_email`     |
| `firstName` | string | `customer_firstname` |
| `lastName`  | string | `customer_lastname`  |

### Address Object (shipping + billing)

| Field        | Type     | Source (ACCS)             |
|--------------|----------|---------------------------|
| `firstName`  | string   | `firstname`               |
| `lastName`   | string   | `lastname`                |
| `street1`    | string   | `street[0]`               |
| `street2`    | string   | `street[1]` (optional)    |
| `city`       | string   | `city`                    |
| `state`      | string   | `region` / `region_code`  |
| `postalCode` | string   | `postcode`                |
| `country`    | string   | `country_id` (e.g. `US`)  |
| `phone`      | string   | `telephone` (optional)    |

### Line Item Object

| Field         | Type   | Source (ACCS)                        | Notes                                    |
|---------------|--------|--------------------------------------|------------------------------------------|
| `sku`         | string | `sku`                                |                                          |
| `name`        | string | `name`                               |                                          |
| `fulfillment` | string | ACCS `fulfillment` custom attribute  | `CPPC` \| `Cart` \| `MTL`               |
| `supplier`    | string | derived from fulfillment map         | `nutralogics` \| `cartcom` \| `mtl`     |
| `quantity`    | number | `qty_ordered`                        |                                          |
| `unitPrice`   | number | `price`                              |                                          |
| `lineTotal`   | number | `row_total`                          |                                          |
| `weight`      | number | `weight` (optional)                  |                                          |
| `productId`   | number | `product_id` (optional)              |                                          |

### Service Bus Message Envelope

Each message published to the `orders.canonical` topic is a **per-supplier slice** — containing only the items for that supplier. The `supplier` user property enables SQL filter routing.

```json
{
  "messageId": "accs-000000087-nutralogics",
  "subject": "order.placed",
  "applicationProperties": {
    "supplier": "nutralogics",
    "orderId": "000000087",
    "environment": "stage",
    "itemCount": 2
  },
  "body": {
    "canonicalId": "uuid-v4",
    "orderId": "000000087",
    "supplier": "nutralogics",
    "placedAt": "2026-06-15T18:25:06Z",
    "customer": { "email": "...", "firstName": "...", "lastName": "..." },
    "shippingAddress": { ... },
    "items": [ /* only nutralogics items */ ],
    "subtotal": 58.20,
    "shippingAmount": 4.41,
    "taxAmount": 0.00,
    "grandTotal": 62.61,
    "currency": "USD"
  }
}
```

---

## Stage 1 — Inbound Adapter (accs-order-webhook)

**Function:** `accs-order-webhook` (existing, to be enhanced)  
**Trigger:** HTTP POST from ACCS webhook

### Processing Steps

1. Validate `x-nutraaxis-webhook-secret` header
2. **Idempotency check** — query `OrderFulfillmentLog` by `orderId`; if already processed, return `200 Already processed`
3. Parse ACCS payload → extract order object
4. Enrich each line item with `fulfillment` attribute from ACCS product API
5. Map enriched order → Canonical Order Model
6. Group items by supplier
7. For each supplier group:
   a. Publish message to `orders.canonical` topic with `supplier` application property
   b. Write to `OrderFulfillmentLog` (status: `published`)
8. Return `200 OK`

---

## Stage 2 — Service Bus Topic

**Namespace:** `sb-forecast-tool`  
**Topic:** `orders.canonical`  
**Message TTL:** 7 days  
**Duplicate Detection:** enabled (window: 24h, key: `messageId`)

### Subscriptions & SQL Filters

| Subscription      | SQL Filter                  | Forwards to        |
|-------------------|-----------------------------|--------------------|
| `sub-nutralogics` | `supplier = 'nutralogics'`  | `to-nutralogics`   |
| `sub-cartcom`     | `supplier = 'cartcom'`      | `to-cartcom`       |
| `sub-mtl`         | `supplier = 'mtl'`          | `to-mtl`           |

---

## Stage 3 — Supplier Queues

| Queue           | Max Delivery Count | Lock Duration | Dead-letter Horizon |
|-----------------|--------------------|---------------|---------------------|
| `to-nutralogics`| 3                  | 5 min         | alert after 1 msg   |
| `to-cartcom`    | 3                  | 5 min         | alert after 1 msg   |
| `to-mtl`        | 3                  | 5 min         | alert after 1 msg   |

---

## Stage 4 — Outbound Adapters

### NutraLogics (`to-nutralogics`)

**Function:** `fulfillment-nutralogics` (Service Bus Queue Trigger)  
**Format:** NutraLogics REST API (TBD)  
**Stub:** email to nutralogics-test@nutraaxislabs.com  

### Cart.com / Jazz (`to-cartcom`)

**Function:** `fulfillment-cartcom` (Service Bus Queue Trigger)  
**Format:** Jazz ASN API (existing credentials in env)  
**Endpoint:** Jazz OMS — same domain/credentials as inventory snapshot  
**Stub:** email to cartcom-test@nutraaxislabs.com  

### MTL (`to-mtl`)

**Function:** `fulfillment-mtl` (Service Bus Queue Trigger)  
**Format:** TBD  
**Stub:** email to mtl-test@nutraaxislabs.com  

---

## OrderFulfillmentLog SQL Table (proposed)

```sql
CREATE TABLE OrderFulfillmentLog (
    Id                 INT IDENTITY PRIMARY KEY,
    OrderId            NVARCHAR(50)   NOT NULL,       -- ACCS increment_id
    OrderEntityId      NVARCHAR(50)   NULL,
    CanonicalId        UNIQUEIDENTIFIER NOT NULL,
    Supplier           NVARCHAR(50)   NOT NULL,       -- nutralogics | cartcom | mtl
    Stage              NVARCHAR(20)   NOT NULL,       -- inbound | published | delivered | failed
    Status             NVARCHAR(20)   NOT NULL,       -- success | error | pending
    MessageId          NVARCHAR(100)  NULL,           -- SB message ID
    ErrorMessage       NVARCHAR(MAX)  NULL,
    RetryCount         INT            DEFAULT 0,
    CreatedAt          DATETIME2      DEFAULT GETUTCDATE(),
    UpdatedAt          DATETIME2      DEFAULT GETUTCDATE(),
    Environment        NVARCHAR(20)   NOT NULL        -- stage | production
);

CREATE UNIQUE INDEX UX_OrderFulfillmentLog_Idempotency
    ON OrderFulfillmentLog (OrderId, Supplier, Environment)
    WHERE Stage = 'published';
```

---

## Monitoring & Alerting

See **Application Insights + Alerting Design** section in architecture canvas.

### Alert Matrix

| Signal                                          | Threshold         | Severity | Action                    |
|-------------------------------------------------|-------------------|----------|---------------------------|
| Dead-letter queue depth > 0 (any supplier queue)| 1 message         | P1       | Email + SMS               |
| Function App failure rate > 5%                  | 5 min window      | P2       | Email                     |
| Inbound webhook HTTP 5xx                        | Any               | P2       | Email                     |
| Order not delivered within 15 min of placement  | 1 order           | P1       | Email                     |
| SQL Server connection failures                  | 3 in 5 min        | P1       | Email                     |
| Service Bus namespace throttling               | Any               | P2       | Email                     |
| Application Insights Smart Detection           | Anomaly           | P2       | Email (built-in)          |

---

## Open Questions

- [ ] NutraLogics API spec / endpoint
- [ ] MTL API spec / endpoint  
- [ ] Should `OrderFulfillmentLog` go in `nutraaxis` or `nutraaxis_staging` DB?
- [ ] Should the topic use Standard or Premium (Premium required for VNet)?
- [ ] Retry policy: 3 attempts then dead-letter, or escalate to Zendesk?
- [ ] Should email stubs remain active in production as CC notifications?

---

## Inventory Management System (IMS)

Physical inventory topology, facility flags, PO receipt rules, and transfer validation are documented in **[IMS Inventory Architecture](./IMS_INVENTORY_ARCHITECTURE.md)**.

Summary:

- **CART** is the mothership — all supplier PO receipts and Jazz ASNs.
- **CPPC** and **WLO** are local IMS spokes replenished only by transfer from CART.
- Facility columns `IsMothership`, `ReceivesPurchaseOrders`, and `IntegrationMode` (`Jazz` vs `Local`) drive validation in `includes/facility.php`.
