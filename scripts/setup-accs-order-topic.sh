#!/usr/bin/env bash
# Create accs-order-canonical-test topic and subscriptions on sb-forecast-tool.
#
# Usage:
#   ./scripts/setup-accs-order-topic.sh
#   RESOURCE_GROUP=rg-forecast-tool NAMESPACE=sb-forecast-tool ./scripts/setup-accs-order-topic.sh
#
# Requires: az login, Service Bus Data Owner or equivalent on the namespace.

set -euo pipefail

RESOURCE_GROUP="${RESOURCE_GROUP:-rg-forecast-tool}"
NAMESPACE="${NAMESPACE:-sb-forecast-tool}"
TOPIC="${ACCS_ORDER_TOPIC:-accs-order-canonical-test}"

create_subscription() {
  local name="$1"
  local filter="$2"

  echo "Creating subscription ${name} filter: ${filter}"
  az servicebus topic subscription create \
    --resource-group "${RESOURCE_GROUP}" \
    --namespace-name "${NAMESPACE}" \
    --topic-name "${TOPIC}" \
    --name "${name}" \
    --max-delivery-count 10 \
    --lock-duration PT5M \
    --enable-dead-lettering-on-message-expiration true \
    --status Active \
    -o none 2>/dev/null || true

  az servicebus topic subscription rule create \
    --resource-group "${RESOURCE_GROUP}" \
    --namespace-name "${NAMESPACE}" \
    --topic-name "${TOPIC}" \
    --subscription-name "${name}" \
    --name "${name}-filter" \
    --filter-sql-expression "${filter}" \
    -o none 2>/dev/null || \
  az servicebus topic subscription rule update \
    --resource-group "${RESOURCE_GROUP}" \
    --namespace-name "${NAMESPACE}" \
    --topic-name "${TOPIC}" \
    --subscription-name "${name}" \
    --name "${name}-filter" \
    --filter-sql-expression "${filter}" \
    -o none
}

echo "Creating topic ${TOPIC} on ${NAMESPACE}..."
az servicebus topic create \
  --resource-group "${RESOURCE_GROUP}" \
  --namespace-name "${NAMESPACE}" \
  --name "${TOPIC}" \
  --enable-partitioning false \
  --enable-express false \
  --max-size 1024 \
  -o none 2>/dev/null || echo "Topic may already exist — continuing."

create_subscription "sub-cart" "supplier_Cart = 1"
create_subscription "sub-cppc" "supplier_CPPC = 1"
create_subscription "sub-mtl" "supplier_MTL = 1"
create_subscription "sub-sql" "1=1"
create_subscription "sub-qbo" "1=1"

echo ""
echo "Done. Set these app settings on Nutra-forecast-tool:"
echo "  ACCS_ORDER_TOPIC=${TOPIC}"
echo "  ACCS_ORDER_SUB_CART=sub-cart"
echo "  ACCS_ORDER_SUB_CPPC=sub-cppc"
echo "  ACCS_ORDER_SUB_MTL=sub-mtl"
echo "  ACCS_ORDER_SUB_SQL=sub-sql"
echo "  ACCS_ORDER_SUB_QBO=sub-qbo"
