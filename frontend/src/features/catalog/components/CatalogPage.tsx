"use client";

import { Alert, Card, ColorPicker, Form, Input, InputNumber, Radio, Select, Switch, Tabs, Tag } from "antd";
import { useTranslations } from "next-intl";
import { parseAsInteger, parseAsStringLiteral, useQueryState } from "nuqs";
import type { Color, DefectType, Item, ItemCategory, ServiceType, TurnaroundTier, Upcharge } from "@/shared/api/types";
import { useCurrencyUnit } from "@/shared/hooks/useCurrencyUnit";
import { usePermission } from "@/shared/hooks/usePermission";
import { formatNumber, toLatinDigits } from "@/shared/lib/digits";
import { formatMoney } from "@/shared/lib/money";
import { MoneyInput } from "@/shared/ui/MoneyInput";
import { PageHeader } from "@/shared/ui/PageHeader";
import { useCatalogList } from "../api";
import { CatalogCrudTab } from "./CatalogCrudTab";
import { PriceMatrix } from "./PriceMatrix";

const TABS = ["prices", "items", "categories", "services", "upcharges", "tiers", "defects", "colors"] as const;
const UNITS = ["piece", "kg", "sqm"] as const;
const EMOJI = ["👕", "👖", "👗", "👔", "🧥", "🧣", "🧤", "👟", "👜", "🛏️", "🧶", "🪟", "🧸", "🎽", "👘", "🧺"];

/** antd ColorPicker hands back an object; the API wants "#rrggbb" or null. */
function hexOf(value: unknown): string | null {
  if (!value) {
    return null;
  }
  if (typeof value === "string") {
    return value;
  }
  const color = value as { toHexString?: () => string; cleared?: boolean };
  return color.cleared ? null : (color.toHexString?.().slice(0, 7) ?? null);
}

function Swatch({ hex }: { hex: string | null | undefined }) {
  return hex ? (
    <span style={{ display: "inline-block", width: 14, height: 14, borderRadius: 4, background: hex, border: "1px solid rgba(0,0,0,.15)", verticalAlign: "middle" }} />
  ) : null;
}

export function CatalogPage() {
  const t = useTranslations();
  const { can } = usePermission();
  const unit = useCurrencyUnit();
  const editable = can("catalog.manage");
  const [tab, setTab] = useQueryState("tab", parseAsStringLiteral(TABS).withDefault("prices"));
  const [categoryFilter, setCategoryFilter] = useQueryState("category", parseAsInteger);
  const categories = useCatalogList("item-categories");
  const categoryOptions = (categories.data ?? []).map((category) => ({ value: category.id, label: category.name }));
  const categoryName = new Map((categories.data ?? []).map((category) => [category.id, category.name]));

  const name = (
    <Form.Item name="name" label={t("catalog.fields.name")} rules={[{ required: true, message: t("common.required") }, { max: 100 }]}>
      <Input />
    </Form.Item>
  );
  const iconField = (
    <Form.Item name="icon" label={t("catalog.fields.icon")} extra={t("catalog.fields.iconHelp")}>
      <Select allowClear showSearch={false} options={EMOJI.map((emoji) => ({ value: emoji, label: <span style={{ fontSize: 20 }}>{emoji}</span> }))} style={{ width: 120 }} />
    </Form.Item>
  );
  const colorField = (field: string, label: string) => (
    <Form.Item name={field} label={label} getValueFromEvent={(color) => hexOf(color)}>
      <ColorPicker allowClear format="hex" disabledAlpha showText />
    </Form.Item>
  );

  const tabs = [
    { key: "prices", label: t("catalog.tabs.prices"), children: <PriceMatrix editable={editable} /> },
    {
      key: "items",
      label: t("catalog.tabs.items"),
      children: (
        <CatalogCrudTab<"items">
          resource="items"
          editable={editable}
          addBlockedReason={categories.isLoading ? t("common.loading") : categoryOptions.length === 0 ? t("catalog.needCategory") : null}
          filter={categoryFilter ? (item: Item) => item.item_category_id === categoryFilter : undefined}
          toolbar={
            <Select
              allowClear
              placeholder={t("catalog.matrix.allCategories")}
              value={categoryFilter}
              onChange={(value) => setCategoryFilter(value ?? null)}
              options={categoryOptions}
              style={{ width: 220 }}
            />
          }
          defaults={{ unit: "piece", pieces_per_unit: 1, item_category_id: categoryFilter ?? categoryOptions[0]?.value }}
          columns={[
            { key: "name", dataIndex: "name", title: t("catalog.fields.name"), render: (value: string, item: Item) => `${item.icon ?? ""} ${value}`.trim() },
            { key: "category", dataIndex: "item_category_id", title: t("catalog.fields.category"), render: (id: number) => categoryName.get(id) },
            { key: "unit", dataIndex: "unit", title: t("catalog.fields.unit"), render: (value: Item["unit"]) => t(`catalog.units.${value}`) },
            { key: "pieces", dataIndex: "pieces_per_unit", title: t("catalog.fields.piecesPerUnit"), render: (value: number) => formatNumber(value) },
          ]}
          fields={
            <>
              <Form.Item name="item_category_id" label={t("catalog.fields.category")} rules={[{ required: true, message: t("common.required") }]}>
                <Select options={categoryOptions} />
              </Form.Item>
              {name}
              <Form.Item name="unit" label={t("catalog.fields.unit")} rules={[{ required: true, message: t("common.required") }]}>
                <Radio.Group optionType="button" options={UNITS.map((value) => ({ value, label: t(`catalog.units.${value}`) }))} />
              </Form.Item>
              <Form.Item name="pieces_per_unit" label={t("catalog.fields.piecesPerUnit")} extra={t("catalog.fields.piecesPerUnitHelp")}>
                <InputNumber<number> min={1} max={50} />
              </Form.Item>
              {iconField}
            </>
          }
        />
      ),
    },
    {
      key: "categories",
      label: t("catalog.tabs.categories"),
      children: (
        <CatalogCrudTab<"item-categories">
          resource="item-categories"
          editable={editable}
          columns={[
            {
              key: "name",
              dataIndex: "name",
              title: t("catalog.fields.name"),
              render: (value: string, category: ItemCategory) => (
                <span>
                  <Swatch hex={category.color} /> {category.icon} {value}
                </span>
              ),
            },
          ]}
          fields={
            <>
              {name}
              {iconField}
              {colorField("color", t("catalog.fields.color"))}
            </>
          }
        />
      ),
    },
    {
      key: "services",
      label: t("catalog.tabs.services"),
      children: (
        <CatalogCrudTab<"service-types">
          resource="service-types"
          editable={editable}
          columns={[
            { key: "name", dataIndex: "name", title: t("catalog.fields.name"), render: (value: string, service: ServiceType) => <span><Swatch hex={service.color} /> {value}</span> },
            { key: "code", dataIndex: "code", title: t("catalog.fields.code"), render: (value: string) => <Tag className="ltr">{value}</Tag> },
          ]}
          fields={
            <>
              {name}
              <Form.Item
                name="code"
                label={t("catalog.fields.code")}
                extra={t("catalog.fields.codeHelp")}
                normalize={(value: string) => toLatinDigits(value ?? "").toUpperCase()}
                rules={[{ required: true, message: t("common.required") }, { pattern: /^[A-Z0-9]{1,10}$/, message: t("catalog.codeInvalid") }]}
              >
                <Input dir="ltr" maxLength={10} style={{ maxWidth: 160 }} />
              </Form.Item>
              {colorField("color", t("catalog.fields.color"))}
            </>
          }
        />
      ),
    },
    {
      key: "upcharges",
      label: t("catalog.tabs.upcharges"),
      children: (
        <CatalogCrudTab<"upcharges">
          resource="upcharges"
          editable={editable}
          defaults={{ type: "percent", value: 0 }}
          columns={[
            { key: "name", dataIndex: "name", title: t("catalog.fields.name") },
            { key: "type", dataIndex: "type", title: t("catalog.fields.type"), render: (value: Upcharge["type"]) => t(`catalog.upchargeTypes.${value}`) },
            {
              key: "value",
              dataIndex: "value",
              title: t("catalog.fields.value"),
              render: (value: number, upcharge: Upcharge) =>
                upcharge.type === "percent" ? `${formatNumber(value)}٪` : `${formatMoney(value, unit)} ${t(`money.${unit}`)}`,
            },
          ]}
          fields={
            <>
              {name}
              <Form.Item name="type" label={t("catalog.fields.type")} rules={[{ required: true, message: t("common.required") }]}>
                <Radio.Group optionType="button" options={(["percent", "fixed"] as const).map((value) => ({ value, label: t(`catalog.upchargeTypes.${value}`) }))} />
              </Form.Item>
              <Form.Item noStyle dependencies={["type"]}>
                {({ getFieldValue }) =>
                  getFieldValue("type") === "fixed" ? (
                    <Form.Item name="value" label={t("catalog.fields.amount")} rules={[{ required: true, message: t("common.required") }]}>
                      <MoneyInput unit={unit} />
                    </Form.Item>
                  ) : (
                    <Form.Item name="value" label={t("catalog.fields.percent")} rules={[{ required: true, message: t("common.required") }]}>
                      <InputNumber<number> min={0} max={1000} suffix="٪" />
                    </Form.Item>
                  )
                }
              </Form.Item>
            </>
          }
        />
      ),
    },
    {
      key: "tiers",
      label: t("catalog.tabs.tiers"),
      children: (
        <CatalogCrudTab<"turnaround-tiers">
          resource="turnaround-tiers"
          editable={editable}
          defaults={{ duration_hours: 72, surcharge_percent: 0, is_default: false }}
          columns={[
            {
              key: "name",
              dataIndex: "name",
              title: t("catalog.fields.name"),
              render: (value: string, tier: TurnaroundTier) => (
                <span>
                  {value} {tier.is_default && <Tag color="blue">{t("catalog.default")}</Tag>}
                </span>
              ),
            },
            { key: "duration", dataIndex: "duration_hours", title: t("catalog.fields.durationHours"), render: (value: number) => t("catalog.hours", { count: value }) },
            { key: "surcharge", dataIndex: "surcharge_percent", title: t("catalog.fields.surchargePercent"), render: (value: number) => (value ? `+${formatNumber(value)}٪` : "—") },
          ]}
          fields={
            <>
              {name}
              <Form.Item name="duration_hours" label={t("catalog.fields.durationHours")} rules={[{ required: true, message: t("common.required") }]}>
                <InputNumber<number> min={1} max={720} />
              </Form.Item>
              <Form.Item name="surcharge_percent" label={t("catalog.fields.surchargePercent")}>
                <InputNumber<number> min={0} max={500} suffix="٪" />
              </Form.Item>
              <Form.Item name="is_default" label={t("catalog.fields.isDefault")} valuePropName="checked">
                <Switch />
              </Form.Item>
            </>
          }
        />
      ),
    },
    {
      key: "defects",
      label: t("catalog.tabs.defects"),
      children: (
        <CatalogCrudTab<"defect-types">
          resource="defect-types"
          editable={editable}
          defaults={{ kind: "defect" }}
          columns={[
            { key: "name", dataIndex: "name", title: t("catalog.fields.name") },
            {
              key: "kind",
              dataIndex: "kind",
              title: t("catalog.fields.kind"),
              render: (value: DefectType["kind"]) => <Tag color={value === "stain" ? "orange" : "red"}>{t(`catalog.defectKinds.${value}`)}</Tag>,
            },
          ]}
          fields={
            <>
              {name}
              <Form.Item name="kind" label={t("catalog.fields.kind")} rules={[{ required: true, message: t("common.required") }]}>
                <Radio.Group optionType="button" options={(["defect", "stain"] as const).map((value) => ({ value, label: t(`catalog.defectKinds.${value}`) }))} />
              </Form.Item>
            </>
          }
        />
      ),
    },
    {
      key: "colors",
      label: t("catalog.tabs.colors"),
      children: (
        <CatalogCrudTab<"colors">
          resource="colors"
          editable={editable}
          columns={[{ key: "name", dataIndex: "name", title: t("catalog.fields.name"), render: (value: string, color: Color) => <span><Swatch hex={color.hex} /> {value}</span> }]}
          fields={
            <>
              <Form.Item name="name" label={t("catalog.fields.name")} rules={[{ required: true, message: t("common.required") }, { max: 50 }]}>
                <Input />
              </Form.Item>
              {colorField("hex", t("catalog.fields.hex"))}
            </>
          }
        />
      ),
    },
  ];

  return (
    <>
      <PageHeader title={t("catalog.title")} />
      {!editable && <Alert type="info" showIcon title={t("catalog.readOnly")} style={{ marginBottom: 16 }} />}
      <Card>
        <Tabs activeKey={tab} onChange={(key) => setTab(key as (typeof TABS)[number])} items={tabs} destroyOnHidden />
      </Card>
    </>
  );
}
