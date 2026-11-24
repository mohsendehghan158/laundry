"use client";

import { DeleteOutlined, TagsOutlined } from "@ant-design/icons";
import { Badge, Button, Card, Drawer, Flex, Input, InputNumber, Select, Tag, Typography } from "antd";
import { useTranslations } from "next-intl";
import { useState } from "react";
import type { CounterCatalog } from "@/shared/api/types";
import { useCurrencyUnit } from "@/shared/hooks/useCurrencyUnit";
import { formatNumber, inputNumberFormatter } from "@/shared/lib/digits";
import { formatMoney, parseAmount } from "@/shared/lib/money";
import { emptyPiece, pieceCount, type CartAction, type CartLine, type CartPiece } from "../cart";

type Props = {
  line: CartLine;
  catalog: CounterCatalog;
  /** This line's total from the server preview, once known. */
  total: number | undefined;
  dispatch: (action: CartAction) => void;
};

export function CartLineCard({ line, catalog, total, dispatch }: Props) {
  const t = useTranslations();
  const unit = useCurrencyUnit();
  const [describing, setDescribing] = useState(false);

  const item = catalog.categories.flatMap((c) => c.items ?? []).find((i) => i.id === line.item_id);
  const service = catalog.service_types.find((s) => s.id === line.service_type_id);
  const pieces = pieceCount(line);
  const described = line.pieces.filter((p) => p.color_id !== null || p.brand || p.notes || p.defect_type_ids.length > 0).length;

  return (
    <Card size="small" styles={{ body: { padding: 12 } }} data-testid="cart-line">
      <Flex justify="space-between" align="start" gap={8}>
        <div>
          <Typography.Text strong>
            {item?.icon ?? ""} {item?.name}
          </Typography.Text>{" "}
          <Tag>{service?.name}</Tag>
        </div>
        <Flex align="center" gap={4}>
          <Typography.Text strong>{total === undefined ? "…" : formatMoney(total, unit)}</Typography.Text>
          <Button type="text" danger size="small" icon={<DeleteOutlined />} aria-label={t("common.delete")} onClick={() => dispatch({ type: "remove", key: line.key })} />
        </Flex>
      </Flex>

      <Flex align="center" gap={8} wrap style={{ marginTop: 8 }}>
        <InputNumber<number>
          size="middle"
          min={line.unit === "piece" ? 1 : 0.1}
          step={line.unit === "piece" ? 1 : 0.5}
          precision={line.unit === "piece" ? 0 : undefined}
          value={line.quantity}
          formatter={inputNumberFormatter}
          parser={(text) => parseAmount(text) ?? 0}
          onChange={(value) => value !== null && dispatch({ type: "setQuantity", key: line.key, quantity: value })}
          suffix={t(`catalog.units.${line.unit}`)}
          style={{ width: 130 }}
          aria-label={t("pos.quantity")}
        />
        <Badge count={described} size="small" color="blue">
          <Button icon={<TagsOutlined />} onClick={() => setDescribing(true)}>
            {t("pos.pieces", { count: pieces })}
          </Button>
        </Badge>
      </Flex>

      {catalog.upcharges.length > 0 && (
        <Flex gap={4} wrap style={{ marginTop: 8 }}>
          {catalog.upcharges.map((upcharge) => (
            <Tag.CheckableTag
              key={upcharge.id}
              checked={line.upcharge_ids.includes(upcharge.id)}
              onChange={() => dispatch({ type: "toggleUpcharge", key: line.key, upchargeId: upcharge.id })}
              style={{ border: "1px solid rgba(0,0,0,.1)" }}
            >
              {upcharge.name} {upcharge.type === "percent" ? `+${formatNumber(upcharge.value)}٪` : `+${formatMoney(upcharge.value, unit)}`}
            </Tag.CheckableTag>
          ))}
        </Flex>
      )}

      <Input
        size="small"
        variant="borderless"
        placeholder={t("pos.lineNotes")}
        value={line.notes}
        onChange={(event) => dispatch({ type: "setLineNotes", key: line.key, notes: event.target.value })}
        style={{ marginTop: 4, paddingInline: 0 }}
        maxLength={500}
      />

      <PieceDrawer
        open={describing}
        title={`${item?.name ?? ""} · ${service?.name ?? ""}`}
        count={pieces}
        pieces={line.pieces}
        catalog={catalog}
        onClose={() => setDescribing(false)}
        onChange={(next) => dispatch({ type: "setPieces", key: line.key, pieces: next })}
      />
    </Card>
  );
}

function PieceDrawer({
  open,
  title,
  count,
  pieces,
  catalog,
  onClose,
  onChange,
}: {
  open: boolean;
  title: string;
  count: number;
  pieces: CartPiece[];
  catalog: CounterCatalog;
  onClose: () => void;
  onChange: (pieces: CartPiece[]) => void;
}) {
  const t = useTranslations("pos");
  const rows = Array.from({ length: count }, (_, index) => pieces[index] ?? emptyPiece());

  const set = (index: number, patch: Partial<CartPiece>) => {
    const next = rows.map((piece, i) => (i === index ? { ...piece, ...patch } : piece));
    onChange(next);
  };

  return (
    <Drawer open={open} onClose={onClose} title={title} size="large" placement="left">
      <Typography.Paragraph type="secondary">{t("piecesHelp")}</Typography.Paragraph>
      <Flex vertical gap={12}>
        {rows.map((piece, index) => (
          <Card key={index} size="small" title={t("pieceNumber", { number: index + 1 })}>
            <Flex vertical gap={8}>
              <Flex gap={8} wrap>
                <Select
                  allowClear
                  placeholder={t("color")}
                  value={piece.color_id}
                  onChange={(value) => set(index, { color_id: value ?? null })}
                  options={catalog.colors.map((color) => ({
                    value: color.id,
                    label: (
                      <span>
                        {color.hex && <span style={{ display: "inline-block", width: 12, height: 12, borderRadius: 3, background: color.hex, border: "1px solid rgba(0,0,0,.2)", marginInlineEnd: 6 }} />}
                        {color.name}
                      </span>
                    ),
                  }))}
                  style={{ width: 160 }}
                  aria-label={t("color")}
                />
                <Input placeholder={t("brand")} value={piece.brand} onChange={(e) => set(index, { brand: e.target.value })} style={{ width: 160 }} maxLength={50} />
              </Flex>
              <Flex gap={4} wrap>
                {catalog.defect_types.map((defect) => (
                  <Tag.CheckableTag
                    key={defect.id}
                    checked={piece.defect_type_ids.includes(defect.id)}
                    onChange={(checked) =>
                      set(index, {
                        defect_type_ids: checked ? [...piece.defect_type_ids, defect.id] : piece.defect_type_ids.filter((id) => id !== defect.id),
                      })
                    }
                    style={{ border: `1px solid ${defect.kind === "stain" ? "#fa8c16" : "#f5222d"}55` }}
                  >
                    {defect.name}
                  </Tag.CheckableTag>
                ))}
              </Flex>
              <Input placeholder={t("pieceNotes")} value={piece.notes} onChange={(e) => set(index, { notes: e.target.value })} maxLength={500} />
            </Flex>
          </Card>
        ))}
      </Flex>
    </Drawer>
  );
}
