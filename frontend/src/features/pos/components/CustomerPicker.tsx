"use client";

import { BankOutlined, SwapOutlined, UserAddOutlined, WarningFilled } from "@ant-design/icons";
import { Alert, Button, Card, Empty, Flex, Select, Spin, Tag, Typography } from "antd";
import { useTranslations } from "next-intl";
import { useEffect, useRef, useState } from "react";
import { looksLikePhone, useCustomer, useCustomers } from "@/features/customers/api";
import { CustomerFormModal } from "@/features/customers/components/CustomerFormModal";
import { usePermission } from "@/shared/hooks/usePermission";
import { useDebouncedValue } from "@/shared/hooks/useDebouncedValue";
import { useCurrencyUnit } from "@/shared/hooks/useCurrencyUnit";
import { toLatinDigits } from "@/shared/lib/digits";
import { formatMoney } from "@/shared/lib/money";

type Props = {
  customerId: number | null;
  onChange: (customerId: number | null) => void;
  /** For customers of a company: the department the order is charged to. */
  departmentId?: number | null;
  onDepartmentChange?: (departmentId: number | null) => void;
};

/**
 * Finds the customer by typing part of a name or mobile number, or
 * registers a new one with what was typed. Once chosen, shows their alert.
 */
export function CustomerPicker({ customerId, onChange, departmentId = null, onDepartmentChange }: Props) {
  const t = useTranslations("pos");
  const tm = useTranslations("money");
  const unit = useCurrencyUnit();
  const { can } = usePermission();
  const [search, setSearch] = useState("");
  const [creating, setCreating] = useState(false);
  const term = useDebouncedValue(search.trim(), 250);
  const results = useCustomers({ q: term, per_page: 8 }, term.length >= 2);
  const selected = useCustomer(customerId);
  const selectRef = useRef<{ focus: () => void } | null>(null);

  useEffect(() => {
    if (customerId === null) {
      selectRef.current?.focus();
    }
  }, [customerId]);

  if (customerId !== null) {
    const customer = selected.data;
    return (
      <Card size="small" styles={{ body: { padding: 12 } }}>
        {!customer ? (
          <Spin />
        ) : (
          <Flex vertical gap={8}>
            <Flex justify="space-between" align="center" gap={8}>
              <div>
                <Typography.Text strong style={{ fontSize: 16 }}>
                  {customer.name}
                </Typography.Text>
                <br />
                <Typography.Text type="secondary" className="ltr">
                  {customer.mobile}
                </Typography.Text>
              </div>
              <Button icon={<SwapOutlined />} onClick={() => onChange(null)}>
                {t("changeCustomer")}
              </Button>
            </Flex>
            {customer.alert && <Alert type="warning" showIcon icon={<WarningFilled />} title={customer.alert} />}
            {customer.company && (
              <Flex vertical gap={6}>
                <Flex gap={6} align="center" wrap>
                  <Tag icon={<BankOutlined />} color="blue">
                    {customer.company.name}
                  </Tag>
                  {customer.company.on_account && customer.company.credit_available !== null && (
                    <Typography.Text type={customer.company.credit_available > 0 ? "secondary" : "danger"} style={{ fontSize: 12 }}>
                      {t("creditAvailable", { amount: `${formatMoney(customer.company.credit_available, unit)} ${tm(unit)}` })}
                    </Typography.Text>
                  )}
                </Flex>
                {onDepartmentChange && (customer.company.departments ?? []).length > 0 && (
                  <Select
                    allowClear
                    size="small"
                    placeholder={t("department")}
                    aria-label={t("department")}
                    value={departmentId}
                    onChange={(value?: number) => onDepartmentChange(value ?? null)}
                    options={(customer.company.departments ?? []).map((d) => ({ value: d.id, label: d.name }))}
                  />
                )}
              </Flex>
            )}
          </Flex>
        )}
      </Card>
    );
  }

  const typed = search.trim();
  const initial = looksLikePhone(typed) ? { mobile: toLatinDigits(typed).replace(/[\s-]/g, "") } : { name: typed };

  return (
    <>
      <Select
        ref={selectRef as never}
        showSearch
        size="large"
        style={{ width: "100%" }}
        placeholder={t("customerSearch")}
        filterOption={false}
        searchValue={search}
        onSearch={setSearch}
        onChange={(value: number) => onChange(value)}
        loading={results.isFetching}
        value={null}
        options={(term.length >= 2 ? (results.data?.data ?? []) : []).map((customer) => ({
          value: customer.id,
          label: (
            <Flex justify="space-between" gap={8}>
              <span>
                {customer.name} {customer.alert && <WarningFilled style={{ color: "#faad14" }} />}
              </span>
              <span className="ltr" style={{ opacity: 0.7 }}>
                {customer.mobile}
              </span>
            </Flex>
          ),
        }))}
        notFoundContent={
          term.length < 2 ? (
            <Typography.Text type="secondary">{t("customerSearchHint")}</Typography.Text>
          ) : results.isFetching ? (
            <Spin size="small" />
          ) : (
            <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description={t("customerNotFound")} />
          )
        }
        popupRender={(menu) => (
          <>
            {menu}
            {can("customers.manage") && (
              <Button type="link" icon={<UserAddOutlined />} onMouseDown={(event) => event.preventDefault()} onClick={() => setCreating(true)}>
                {typed ? t("registerWith", { text: typed }) : t("registerNew")}
              </Button>
            )}
          </>
        )}
        aria-label={t("customerSearch")}
      />
      <CustomerFormModal
        open={creating}
        initial={initial}
        onClose={() => setCreating(false)}
        onSaved={(customer) => {
          setSearch("");
          onChange(customer.id);
        }}
      />
    </>
  );
}
