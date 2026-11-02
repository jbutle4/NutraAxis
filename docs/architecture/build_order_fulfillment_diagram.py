"""Generate the Order Fulfillment conceptual architecture diagram.

Outputs (same layout, same folder):
  order-fulfillment-architecture.drawio  - open in diagrams.net / draw.io; File > Export as > VSDX for Visio
  order-fulfillment-architecture.svg     - Visio can open SVG directly (File > Open)

Run: python3 docs/architecture/build_order_fulfillment_diagram.py
"""
from html import escape
from pathlib import Path

OUT_DIR = Path(__file__).resolve().parent
NAME = "order-fulfillment-architecture"
W, H = 1650, 960
FONT = "Segoe UI, Helvetica Neue, Arial, sans-serif"

THEMES = {
    "external":  {"fill": "#F3F4F6", "stroke": "#4B5563", "title": "#111827"},
    "function":  {"fill": "#E8F1FB", "stroke": "#0078D4", "title": "#004E8C"},
    "bus":       {"fill": "#FFFFFF", "stroke": "#B7791F", "title": "#7C4A03"},
    "busgroup":  {"fill": "#FFF8E6", "stroke": "#B7791F", "title": "#7C4A03"},
    "sql":       {"fill": "#EEF7EE", "stroke": "#2E7D32", "title": "#1B5E20"},
    "monitor":   {"fill": "#FFFFFF", "stroke": "#7B3FA0", "title": "#5B2C83"},
    "monitorgroup": {"fill": "#F7F0FC", "stroke": "#7B3FA0", "title": "#5B2C83"},
    "legend":    {"fill": "#FFFFFF", "stroke": "#9CA3AF", "title": "#111827"},
}

EDGE_STYLES = {
    "flow":      {"color": "#1F2937", "dash": None,  "width": 1.8},
    "lookup":    {"color": "#4B5563", "dash": "2 4", "width": 1.5},
    "log":       {"color": "#2E7D32", "dash": "6 4", "width": 1.5},
    "telemetry": {"color": "#7B3FA0", "dash": "6 4", "width": 1.5},
}

# id, theme, x, y, w, h, title, lines, group(bool)
NODES = [
    ("accs", "external", 20, 290, 170, 150, "Adobe Commerce (ACCS)",
     ["Order source (storefront)", "Adobe I/O Events webhook", "REST API: product attrs"]),
    ("inbound", "function", 250, 220, 220, 290, "Stage 1 · Inbound Adapter",
     ["Azure Function · HTTP trigger", "accs-order-webhook", "",
      "• Validate ACCS signature", "• Idempotency check (orderId)", "• Enrich SKU → fulfillment",
      "• Map → Canonical Order", "• Split items by supplier", "• Publish 1 msg / supplier",
      "• Write OrderFulfillmentLog"]),
    ("sql", "sql", 250, 560, 200, 90, "Azure SQL",
     ["OrderFulfillmentLog", "Idempotency + audit trail"]),

    ("sbns", "busgroup", 530, 130, 570, 480, "Azure Service Bus · sb-forecast-tool (Standard)", [], True),
    ("topic", "bus", 550, 175, 290, 85, "Stage 2 · Topic: orders.canonical",
     ["App property: supplier", "Duplicate detection 24h · TTL 7d"]),
    ("sub_nl", "bus", 600, 300, 230, 62, "sub-nutralogics", ["SQL filter: supplier = 'nutralogics'"]),
    ("sub_cc", "bus", 600, 400, 230, 62, "sub-cartcom",     ["SQL filter: supplier = 'cartcom'"]),
    ("sub_mtl", "bus", 600, 500, 230, 62, "sub-mtl",        ["SQL filter: supplier = 'mtl'"]),
    ("q_nl", "bus", 900, 300, 180, 62, "Stage 3 · to-nutralogics", ["Max delivery 3 → DLQ"]),
    ("q_cc", "bus", 900, 400, 180, 62, "Stage 3 · to-cartcom",     ["Max delivery 3 → DLQ"]),
    ("q_mtl", "bus", 900, 500, 180, 62, "Stage 3 · to-mtl",        ["Max delivery 3 → DLQ"]),

    ("ad_nl", "function", 1140, 296, 210, 70, "fulfillment-nutralogics",
     ["Function · SB queue trigger", "Map canonical → NL format"]),
    ("ad_cc", "function", 1140, 396, 210, 70, "fulfillment-cartcom",
     ["Function · SB queue trigger", "Map canonical → Jazz format"]),
    ("ad_mtl", "function", 1140, 496, 210, 70, "fulfillment-mtl",
     ["Function · SB queue trigger", "Map canonical → MTL format"]),

    ("sup_nl", "external", 1410, 296, 215, 70, "NutraLogics",
     ["fulfillment = CPPC", "API TBD · Phase 1: email stub"]),
    ("sup_cc", "external", 1410, 396, 215, 70, "Cart.com (Jazz)",
     ["fulfillment = Cart", "Jazz API (ASN creds) · email stub"]),
    ("sup_mtl", "external", 1410, 496, 215, 70, "MTL",
     ["fulfillment = MTL", "API TBD · Phase 1: email stub"]),

    ("legend", "legend", 1410, 120, 215, 140, "Legend", []),

    ("mon", "monitorgroup", 20, 725, 1605, 205, "Monitoring & Alerting", [], True),
    ("appi", "monitor", 45, 770, 330, 135, "Application Insights",
     ["appi-forecast-tool", "Requests · exceptions · dependencies", "Custom events: order.received,",
      "order.published, order.delivered"]),
    ("smart", "monitor", 415, 770, 260, 135, "Smart Detection",
     ["Failure anomalies", "Performance degradation", "Memory leak · security"]),
    ("rules", "monitor", 715, 770, 380, 135, "Azure Monitor alert rules",
     ["Sev 1: SB dead-letter count > 0", "Sev 1: order not delivered in 15 min (KQL)",
      "Sev 1: SQL availability test (every 5 min)", "Sev 2: Function failure rate > 5%",
      "Sev 2: SB throttling · Function App down"]),
    ("ag", "monitor", 1135, 770, 220, 135, "Action Group",
     ["P1: Email + SMS", "P2: Email"]),
    ("ops", "monitor", 1395, 770, 205, 135, "Ops / on-call",
     ["Investigate · replay DLQ", "OrderFulfillmentLog lookup"]),
]

STAGE_LABELS = [
    (105, "SOURCE"), (360, "STAGE 1 · INBOUND"), (695, "STAGE 2 · TOPIC"),
    (990, "STAGE 3 · QUEUES"), (1245, "STAGE 4 · OUTBOUND"), (1517, "SUPPLIERS"),
]

# kind, source, target, points (absolute, first/last on node borders), label, label_xy
EDGES = [
    ("flow", "accs", "inbound", [(190, 335), (250, 335)], "HTTPS POST", (220, 326)),
    ("lookup", "inbound", "accs", [(250, 410), (190, 410)], "SKU lookup", (220, 428)),
    ("flow", "inbound", "topic", [(470, 260), (510, 260), (510, 218), (550, 218)], "publish", (488, 250)),
    ("flow", "topic", "sub_nl", [(575, 260), (575, 331), (600, 331)], "", None),
    ("flow", "topic", "sub_cc", [(575, 260), (575, 431), (600, 431)], "", None),
    ("flow", "topic", "sub_mtl", [(575, 260), (575, 531), (600, 531)], "", None),
    ("flow", "sub_nl", "q_nl", [(830, 331), (900, 331)], "forward", (865, 322)),
    ("flow", "sub_cc", "q_cc", [(830, 431), (900, 431)], "forward", (865, 422)),
    ("flow", "sub_mtl", "q_mtl", [(830, 531), (900, 531)], "forward", (865, 522)),
    ("flow", "q_nl", "ad_nl", [(1080, 331), (1140, 331)], "trigger", (1110, 322)),
    ("flow", "q_cc", "ad_cc", [(1080, 431), (1140, 431)], "trigger", (1110, 422)),
    ("flow", "q_mtl", "ad_mtl", [(1080, 531), (1140, 531)], "trigger", (1110, 522)),
    ("flow", "ad_nl", "sup_nl", [(1350, 331), (1410, 331)], "send", (1380, 322)),
    ("flow", "ad_cc", "sup_cc", [(1350, 431), (1410, 431)], "send", (1380, 422)),
    ("flow", "ad_mtl", "sup_mtl", [(1350, 531), (1410, 531)], "send", (1380, 522)),
    ("log", "inbound", "sql", [(350, 510), (350, 560)], "write log", (390, 540)),
    ("log", "ad_mtl", "sql", [(1200, 566), (1200, 685), (350, 685), (350, 650)],
     "log delivery result (all outbound adapters)", (780, 678)),
    ("telemetry", "inbound", "mon", [(462, 510), (462, 725)], "Function telemetry", (530, 712)),
    ("telemetry", "sbns", "mon", [(1000, 610), (1000, 725)], "SB metrics", (1045, 712)),
    ("telemetry", "ad_mtl", "mon", [(1320, 566), (1320, 725)], "Function telemetry", (1388, 712)),
    ("telemetry", "appi", "smart", [(375, 837), (415, 837)], "", None),
    ("telemetry", "smart", "rules", [(675, 837), (715, 837)], "", None),
    ("telemetry", "rules", "ag", [(1095, 837), (1135, 837)], "", None),
    ("telemetry", "ag", "ops", [(1355, 837), (1395, 837)], "", None),
]

LEGEND_ITEMS = [
    ("flow", "Order flow"), ("lookup", "Lookup (read)"),
    ("log", "Log write"), ("telemetry", "Telemetry / alert"),
]

TITLE = "NutraAxis · Order Fulfillment Integration Architecture (Conceptual)"
SUBTITLE = ("Event-driven routing of ACCS orders to fulfillment suppliers by the product "
            "'fulfillment' attribute · Draft 2026-10-09")


def node_by_id(nid):
    for n in NODES:
        if n[0] == nid:
            return n
    raise KeyError(nid)


# ---------------------------------------------------------------- SVG
def svg_text(x, y, text, size=11, weight="normal", color="#374151", anchor="start", italic=False):
    style = ' font-style="italic"' if italic else ""
    return (f'<text x="{x}" y="{y}" font-family="{FONT}" font-size="{size}" font-weight="{weight}" '
            f'fill="{color}" text-anchor="{anchor}"{style}>{escape(text)}</text>')


def build_svg():
    out = [f'<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{H}" viewBox="0 0 {W} {H}">',
           "<defs>"]
    for kind, s in EDGE_STYLES.items():
        out.append(f'<marker id="arrow-{kind}" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" '
                   f'markerHeight="7" orient="auto-start-reverse"><path d="M0,0 L10,5 L0,10 z" '
                   f'fill="{s["color"]}"/></marker>')
    out.append("</defs>")
    out.append(f'<rect width="{W}" height="{H}" fill="#FFFFFF"/>')
    out.append(svg_text(20, 38, TITLE, 20, "600", "#111827"))
    out.append(svg_text(20, 60, SUBTITLE, 12, "normal", "#6B7280"))
    for cx, label in STAGE_LABELS:
        out.append(svg_text(cx, 100, label, 11, "700", "#6B7280", "middle"))
    out.append('<line x1="20" y1="110" x2="1625" y2="110" stroke="#E5E7EB" stroke-width="1"/>')

    groups = [n for n in NODES if len(n) > 8 and n[8]]
    others = [n for n in NODES if not (len(n) > 8 and n[8])]
    for n in groups + others:
        nid, theme, x, y, w, h, title, lines = n[:8]
        t = THEMES[theme]
        is_group = len(n) > 8 and n[8]
        dash = ' stroke-dasharray="8 5"' if is_group else ""
        out.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="8" fill="{t["fill"]}" '
                   f'stroke="{t["stroke"]}" stroke-width="1.5"{dash}/>')
        if is_group:
            out.append(svg_text(x + 14, y + 22, title, 13, "700", t["title"]))
            continue
        if nid == "legend":
            out.append(svg_text(x + 12, y + 22, title, 13, "700", t["title"]))
            for i, (kind, label) in enumerate(LEGEND_ITEMS):
                ly = y + 48 + i * 24
                s = EDGE_STYLES[kind]
                dash_attr = f' stroke-dasharray="{s["dash"]}"' if s["dash"] else ""
                out.append(f'<line x1="{x + 14}" y1="{ly - 4}" x2="{x + 64}" y2="{ly - 4}" stroke="{s["color"]}" '
                           f'stroke-width="{s["width"]}"{dash_attr} marker-end="url(#arrow-{kind})"/>')
                out.append(svg_text(x + 76, ly, label, 11))
            continue
        out.append(f'<rect x="{x}" y="{y}" width="6" height="{h}" rx="3" fill="{t["stroke"]}"/>')
        out.append(svg_text(x + 16, y + 22, title, 13, "700", t["title"]))
        for i, line in enumerate(lines):
            if not line:
                continue
            color = "#B45309" if "stub" in line else "#374151"
            italic = line.startswith("accs-order-webhook") or line == "appi-forecast-tool"
            out.append(svg_text(x + 16, y + 42 + i * 17, line, 11, "normal", color, italic=italic))

    for kind, _src, _tgt, pts, label, lxy in EDGES:
        s = EDGE_STYLES[kind]
        d = "M" + " L".join(f"{px},{py}" for px, py in pts)
        dash_attr = f' stroke-dasharray="{s["dash"]}"' if s["dash"] else ""
        out.append(f'<path d="{d}" fill="none" stroke="{s["color"]}" stroke-width="{s["width"]}"'
                   f'{dash_attr} marker-end="url(#arrow-{kind})"/>')
        if label and lxy:
            tw = len(label) * 6 + 8
            out.append(f'<rect x="{lxy[0] - tw / 2}" y="{lxy[1] - 11}" width="{tw}" height="15" fill="#FFFFFF" '
                       f'fill-opacity="0.9"/>')
            out.append(svg_text(lxy[0], lxy[1], label, 10, "600", s["color"], "middle"))

    out.append(svg_text(20, H - 12, "Phase 1: outbound adapters send email stubs. Phase 2: replace with "
                        "supplier APIs (Jazz reuses existing ASN credentials).", 11, "normal", "#6B7280"))
    out.append("</svg>")
    return "\n".join(out)


# ---------------------------------------------------------------- draw.io
def drawio_value(title, lines):
    body = "<br>".join(escape(l) if l else "" for l in lines)
    html = f"<b>{escape(title)}</b>"
    if body:
        html += f"<br><font style='font-size:11px'>{body}</font>"
    return escape(html, quote=True)


def build_drawio():
    cells = ['<mxCell id="0"/>', '<mxCell id="1" parent="0"/>']

    def vertex(cid, value, style, x, y, w, h):
        cells.append(f'<mxCell id="{cid}" value="{value}" style="{style}" vertex="1" parent="1">'
                     f'<mxGeometry x="{x}" y="{y}" width="{w}" height="{h}" as="geometry"/></mxCell>')

    vertex("title", escape(f"<b style='font-size:20px'>{escape(TITLE)}</b><br>"
                           f"<font color='#6B7280'>{escape(SUBTITLE)}</font>", quote=True),
           "text;html=1;align=left;verticalAlign=top;whiteSpace=wrap;", 20, 15, 1200, 55)
    for cx, label in STAGE_LABELS:
        vertex(f"stage_{cx}", escape(label), "text;html=1;align=center;fontStyle=1;fontColor=#6B7280;fontSize=11;",
               cx - 90, 88, 180, 20)

    groups = [n for n in NODES if len(n) > 8 and n[8]]
    others = [n for n in NODES if not (len(n) > 8 and n[8]) and n[0] != "legend"]
    for n in groups + others:
        nid, theme, x, y, w, h, title, lines = n[:8]
        t = THEMES[theme]
        is_group = len(n) > 8 and n[8]
        style = (f"rounded=1;arcSize=4;whiteSpace=wrap;html=1;fillColor={t['fill']};strokeColor={t['stroke']};"
                 f"strokeWidth=1.5;fontColor=#374151;fontSize=11;align=left;verticalAlign=top;spacingLeft=12;"
                 f"spacingTop=6;")
        if is_group:
            style += "dashed=1;dashPattern=8 5;fontStyle=1;fontSize=13;container=0;"
            vertex(nid, escape(title), style + f"fontColor={t['title']};", x, y, w, h)
        else:
            vertex(nid, drawio_value(title, lines), style, x, y, w, h)

    lx, ly = 1410, 120
    vertex("legend", escape("<b>Legend</b>"), "rounded=1;arcSize=4;whiteSpace=wrap;html=1;fillColor=#FFFFFF;"
           "strokeColor=#9CA3AF;align=left;verticalAlign=top;spacingLeft=12;spacingTop=6;", lx, ly, 215, 140)
    for i, (kind, label) in enumerate(LEGEND_ITEMS):
        s = EDGE_STYLES[kind]
        y0 = ly + 44 + i * 24
        dash = "dashed=1;" + (f"dashPattern={s['dash']};" if s["dash"] else "") if s["dash"] else ""
        cells.append(f'<mxCell id="lg_{kind}" style="endArrow=block;endFill=1;html=1;strokeColor={s["color"]};'
                     f'strokeWidth={s["width"]};{dash}" edge="1" parent="1"><mxGeometry relative="1" as="geometry">'
                     f'<mxPoint x="{lx + 14}" y="{y0}" as="sourcePoint"/><mxPoint x="{lx + 64}" y="{y0}" '
                     f'as="targetPoint"/></mxGeometry></mxCell>')
        vertex(f"lgt_{kind}", escape(label), "text;html=1;align=left;fontSize=11;", lx + 72, y0 - 10, 130, 20)

    for i, (kind, src, tgt, pts, label, _lxy) in enumerate(EDGES):
        s = EDGE_STYLES[kind]
        sn, tn = node_by_id(src), node_by_id(tgt)
        (sx, sy), (tx, ty) = pts[0], pts[-1]
        exit_x, exit_y = (sx - sn[2]) / sn[4], (sy - sn[3]) / sn[5]
        entry_x, entry_y = (tx - tn[2]) / tn[4], (ty - tn[3]) / tn[5]
        dash = "dashed=1;" + f"dashPattern={s['dash']};" if s["dash"] else ""
        style = (f"edgeStyle=orthogonalEdgeStyle;rounded=0;html=1;endArrow=block;endFill=1;"
                 f"strokeColor={s['color']};strokeWidth={s['width']};{dash}fontColor={s['color']};"
                 f"fontSize=10;fontStyle=1;labelBackgroundColor=#FFFFFF;"
                 f"exitX={exit_x:.4f};exitY={exit_y:.4f};exitDx=0;exitDy=0;"
                 f"entryX={entry_x:.4f};entryY={entry_y:.4f};entryDx=0;entryDy=0;")
        waypoints = "".join(f'<mxPoint x="{px}" y="{py}"/>' for px, py in pts[1:-1])
        array = f'<Array as="points">{waypoints}</Array>' if waypoints else ""
        cells.append(f'<mxCell id="e{i}" value="{escape(label)}" style="{style}" edge="1" parent="1" '
                     f'source="{src}" target="{tgt}"><mxGeometry relative="1" as="geometry">{array}'
                     f'</mxGeometry></mxCell>')

    vertex("footer", escape("Phase 1: outbound adapters send email stubs. Phase 2: replace with supplier APIs "
                            "(Jazz reuses existing ASN credentials)."),
           "text;html=1;align=left;fontSize=11;fontColor=#6B7280;", 20, H - 30, 900, 20)

    return ('<?xml version="1.0" encoding="UTF-8"?>\n'
            '<mxfile host="app.diagrams.net" type="device">'
            f'<diagram id="order-fulfillment" name="Order Fulfillment Architecture">'
            f'<mxGraphModel dx="{W}" dy="{H}" grid="1" gridSize="10" guides="1" tooltips="1" connect="1" '
            f'arrows="1" fold="1" page="1" pageScale="1" pageWidth="{W}" pageHeight="{H}" math="0" shadow="0">'
            f'<root>{"".join(cells)}</root></mxGraphModel></diagram></mxfile>\n')


if __name__ == "__main__":
    (OUT_DIR / f"{NAME}.svg").write_text(build_svg(), encoding="utf-8")
    (OUT_DIR / f"{NAME}.drawio").write_text(build_drawio(), encoding="utf-8")
    print(f"Wrote {OUT_DIR / (NAME + '.svg')}")
    print(f"Wrote {OUT_DIR / (NAME + '.drawio')}")
