"use client";

import { PlusOutlined } from "@ant-design/icons";
import { Button, Card, Flex, Input, Tag, Typography } from "antd";
import type { ColumnsType } from "antd/es/table";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { useState } from "react";
import type { Company } from "@/shared/api/types";
import { useCurrencyUnit } from "@/shared/hooks/useCurrencyUnit";
import { useListParams } from "@/shared/hooks/useListParams";
import { usePermission } from "@/shared/hooks/usePermission";
import { formatDate } from "@/shared/lib/date";
import { formatMoney } from "@/shared/lib/money";
import { DataTable } from "@/shared/ui/DataTable";
import { PageHeader } from "@/shared/ui/PageHeader";
import { useCompanies } from "../api";
import { CompanyFormModal } from "./CompanyFormModal";

export function CompaniesPage() {
  const t = useTranslations();
  const router = useRouter();
  const unit = useCurrencyUnit();
  const { can } = usePermission();
  const [creating, setCreating] = useState(false);
  const [params, setParams, setFilter] = useListParams();
  const companies = useCompanies({ q: params.q, page: params.page, per_page: params.per_page });
  const money = (v: number) => `${formatMoney(v, unit)} ${t(`money.${unit}`)}`;

  const columns: ColumnsType<Company> = [
    {
      key: "name",
      title: t("companies.fields.name"),
      render: (_v, company) => (
        <Flex vertical>
          <Link href={`/companies/${company.id}`}>{company.name}</Link>
          {company.contact_name && (
            <Typography.Text type="secondary" style={{ fontSize: 12 }}>
              {company.contact_name} {company.contact_mobile && <span className="ltr">{company.contact_mobile}</span>}
            </Typography.Text>
          )}
        </Flex>
      ),
    },
    {
      key: "contract",
      title: t("companies.contract"),
      render: (_v, company) => (
        <Flex gap={4} wrap>
          {company.price_list && <Tag color="purple">{company.price_list.name}</Tag>}
          {company.contract_ends_on && <Tag color={company.contract_running ? undefined : "red"}>{t("companies.until", { date: formatDate(company.contract_ends_on) })}</Tag>}
        </Flex>
      ),
    },
    {
      key: "credit",
      title: t("companies.fields.creditLimit"),
      render: (_v, company) => (company.credit_limit === null ? <Typography.Text type="secondary">{t("companies.noAccount")}</Typography.Text> : money(company.credit_limit)),
    },
    {
      key: "outstanding",
      title: t("companies.outstanding"),
      render: (_v, company) => <Typography.Text type={(company.outstanding ?? 0) > (company.credit_limit ?? Infinity) ? "danger" : undefined}>{money(company.outstanding ?? 0)}</Typography.Text>,
    },
    { key: "status", title: t("common.status"), render: (_v, company) => (company.is_active ? <Tag color="green">{t("common.active")}</Tag> : <Tag>{t("common.inactive")}</Tag>) },
  ];

  return (
    <>
      <PageHeader
        title={t("companies.title")}
        subtitle={t("companies.subtitle")}
        extra={
          can("companies.manage") && (
            <Button type="primary" icon={<PlusOutlined />} onClick={() => setCreating(true)}>
              {t("companies.new")}
            </Button>
          )
        }
      />
      <Card>
        <Input.Search allowClear defaultValue={params.q} placeholder={t("companies.searchPlaceholder")} onSearch={(q) => setFilter({ q: q || null })} style={{ width: 300, marginBottom: 16 }} />
        <DataTable<Company>
          columns={columns}
          result={companies.data}
          loading={companies.isFetching}
          onPageChange={(page, perPage) => setParams({ page, per_page: perPage })}
          onRow={(company) => ({ onDoubleClick: () => router.push(`/companies/${company.id}`) })}
        />
      </Card>
      <CompanyFormModal company={null} open={creating} onClose={() => setCreating(false)} onSaved={(company) => router.push(`/companies/${company.id}`)} />
    </>
  );
}
