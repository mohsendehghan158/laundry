"use client";

import { DeleteOutlined, EditOutlined, PlusOutlined, UserAddOutlined } from "@ant-design/icons";
import { App, Button, Card, Col, Descriptions, Flex, Input, Popconfirm, Result, Row, Select, Skeleton, Switch, Table, Tag, Typography } from "antd";
import type { ColumnsType } from "antd/es/table";
import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { useState } from "react";
import { useCustomers } from "@/features/customers/api";
import { isApiError } from "@/shared/api/client";
import { errorMessage } from "@/shared/api/formErrors";
import type { Company } from "@/shared/api/types";
import { useCurrencyUnit } from "@/shared/hooks/useCurrencyUnit";
import { useDebouncedValue } from "@/shared/hooks/useDebouncedValue";
import { usePermission } from "@/shared/hooks/usePermission";
import { formatDate } from "@/shared/lib/date";
import { formatNumber, toPersianDigits } from "@/shared/lib/digits";
import { formatMoney } from "@/shared/lib/money";
import { PageHeader } from "@/shared/ui/PageHeader";
import { StatTile } from "@/shared/ui/StatTile";
import { useAddMember, useCompany, useDeleteCompany, useDeleteDepartment, useRemoveMember, useSaveDepartment } from "../api";
import { CompanyFormModal } from "./CompanyFormModal";
import { DeliveryNotesCard } from "./DeliveryNotesCard";

type Department = NonNullable<Company["departments"]>[number];
type Member = NonNullable<Company["members"]>[number];

export function CompanyDetailPage() {
  const t = useTranslations();
  const { message } = App.useApp();
  const router = useRouter();
  const unit = useCurrencyUnit();
  const { can } = usePermission();
  const id = Number(useParams<{ id: string }>().id);
  const company = useCompany(Number.isFinite(id) ? id : null);
  const saveDepartment = useSaveDepartment();
  const deleteDepartment = useDeleteDepartment();
  const addMember = useAddMember();
  const removeMember = useRemoveMember();
  const deleteCompany = useDeleteCompany();
  const [editing, setEditing] = useState(false);
  const [departmentName, setDepartmentName] = useState("");
  const [search, setSearch] = useState("");
  const term = useDebouncedValue(search.trim(), 250);
  const candidates = useCustomers({ q: term, per_page: 8 }, term.length >= 2);
  const manage = can("companies.manage");
  const fail = (error: unknown) => message.error(errorMessage(error, t("common.error")));

  if (company.isLoading) {
    return <Skeleton active />;
  }
  if (company.isError || !company.data) {
    return <Result status={isApiError(company.error, 404) ? "404" : "error"} title={t("companies.notFound")} extra={<Link href="/companies">{t("companies.backToList")}</Link>} />;
  }

  const data = company.data;
  const money = (v: number) => `${formatMoney(v, unit)} ${t(`money.${unit}`)}`;
  const available = data.credit_limit === null ? null : Math.max(0, data.credit_limit - (data.outstanding ?? 0));

  const departmentColumns: ColumnsType<Department> = [
    {
      key: "name",
      dataIndex: "name",
      title: t("companies.fields.department"),
      render: (name: string, department) => (
        <Typography.Text editable={manage ? { tooltip: t("common.edit"), onChange: (next) => next.trim() && next !== name && saveDepartment.mutate({ companyId: data.id, id: department.id, input: { name: next.trim() } }, { onError: fail }) } : false}>
          {name}
        </Typography.Text>
      ),
    },
    {
      key: "code",
      dataIndex: "code",
      title: t("companies.fields.costCenter"),
      render: (code: string | null, department) => (
        <Typography.Text
          className="ltr"
          editable={manage ? { tooltip: t("common.edit"), text: code ?? "", onChange: (next) => next !== (code ?? "") && saveDepartment.mutate({ companyId: data.id, id: department.id, input: { code: next.trim() || null } }, { onError: fail }) } : false}
        >
          {code ?? "—"}
        </Typography.Text>
      ),
    },
    {
      key: "active",
      dataIndex: "is_active",
      title: t("common.active"),
      render: (active: boolean, department) => (
        <Switch size="small" checked={active} disabled={!manage} aria-label={t("common.active")} onChange={(checked) => saveDepartment.mutate({ companyId: data.id, id: department.id, input: { is_active: checked } }, { onError: fail })} />
      ),
    },
    ...(manage
      ? [
          {
            key: "actions",
            width: 60,
            render: (_v: unknown, department: Department) => (
              <Popconfirm title={t("companies.deleteDepartmentConfirm", { name: department.name })} okText={t("common.delete")} cancelText={t("common.cancel")} okButtonProps={{ danger: true }} onConfirm={() => deleteDepartment.mutateAsync({ companyId: data.id, id: department.id }).catch(fail)}>
                <Button type="text" danger icon={<DeleteOutlined />} aria-label={t("common.delete")} />
              </Popconfirm>
            ),
          },
        ]
      : []),
  ];

  const memberColumns: ColumnsType<Member> = [
    { key: "name", title: t("customers.fields.name"), render: (_v, member) => <Link href={`/customers/${member.id}`}>{member.name}</Link> },
    { key: "mobile", dataIndex: "mobile", title: t("customers.fields.mobile"), render: (v: string) => <span className="ltr">{v}</span> },
    ...(manage
      ? [
          {
            key: "actions",
            width: 60,
            render: (_v: unknown, member: Member) => (
              <Popconfirm title={t("companies.removeMemberConfirm", { name: member.name })} okText={t("companies.remove")} cancelText={t("common.cancel")} onConfirm={() => removeMember.mutateAsync({ companyId: data.id, customerId: member.id }).catch(fail)}>
                <Button type="text" danger icon={<DeleteOutlined />} aria-label={t("companies.remove")} />
              </Popconfirm>
            ),
          },
        ]
      : []),
  ];

  return (
    <>
      <PageHeader
        title={
          <Flex align="center" gap={8} wrap>
            {data.name}
            {!data.is_active && <Tag>{t("common.inactive")}</Tag>}
            {data.on_account && <Tag color="blue">{t("companies.fields.onAccount")}</Tag>}
          </Flex>
        }
        subtitle={<Link href="/companies">{t("companies.backToList")}</Link>}
        extra={
          manage && (
            <>
              <Button icon={<EditOutlined />} onClick={() => setEditing(true)}>
                {t("common.edit")}
              </Button>
              <Popconfirm
                title={t("companies.deleteConfirm")}
                okText={t("common.delete")}
                cancelText={t("common.cancel")}
                okButtonProps={{ danger: true }}
                onConfirm={() =>
                  deleteCompany.mutateAsync(data.id).then(() => {
                    message.success(t("common.deleted"));
                    router.push("/companies");
                  }, fail)
                }
              >
                <Button danger icon={<DeleteOutlined />}>
                  {t("common.delete")}
                </Button>
              </Popconfirm>
            </>
          )
        }
      />
      <Row gutter={[16, 16]} style={{ marginBottom: 16 }}>
        <Col xs={12} lg={6}>
          <StatTile title={t("companies.outstanding")} value={formatMoney(data.outstanding ?? 0, unit)} suffix={t(`money.${unit}`)} />
        </Col>
        <Col xs={12} lg={6}>
          <StatTile title={t("companies.fields.creditLimit")} value={data.credit_limit === null ? "—" : formatMoney(data.credit_limit, unit)} suffix={data.credit_limit === null ? undefined : t(`money.${unit}`)} />
        </Col>
        <Col xs={12} lg={6}>
          <StatTile title={t("companies.available")} value={available === null ? "—" : formatMoney(available, unit)} suffix={available === null ? undefined : t(`money.${unit}`)} />
        </Col>
        <Col xs={12} lg={6}>
          <StatTile title={t("companies.fields.paymentTerms")} value={t("companies.days", { count: data.payment_terms_days })} />
        </Col>
      </Row>
      <Row gutter={[16, 16]}>
        <Col xs={24} lg={10}>
          <Card title={t("companies.contract")}>
            <Descriptions column={1} size="small">
              <Descriptions.Item label={t("companies.fields.contractNumber")}>{data.contract_number ?? "—"}</Descriptions.Item>
              <Descriptions.Item label={t("companies.fields.contractPeriod")}>
                {data.contract_starts_on || data.contract_ends_on ? `${data.contract_starts_on ? formatDate(data.contract_starts_on) : "…"} – ${data.contract_ends_on ? formatDate(data.contract_ends_on) : "…"}` : "—"}{" "}
                {!data.contract_running && <Tag color="red">{t("companies.contractEnded")}</Tag>}
              </Descriptions.Item>
              <Descriptions.Item label={t("companies.fields.priceList")}>{data.price_list?.name ?? t("catalog.priceLists.default")}</Descriptions.Item>
              <Descriptions.Item label={t("companies.fields.creditLimit")}>{data.credit_limit === null ? t("companies.noAccount") : money(data.credit_limit)}</Descriptions.Item>
              <Descriptions.Item label={t("invoices.fields.legalId")}>{data.national_id ? toPersianDigits(data.national_id) : "—"}</Descriptions.Item>
              <Descriptions.Item label={t("invoices.fields.economicCode")}>{data.economic_code ? toPersianDigits(data.economic_code) : "—"}</Descriptions.Item>
              <Descriptions.Item label={t("companies.fields.contactName")}>
                {data.contact_name ?? "—"} {data.contact_mobile && <span className="ltr">{data.contact_mobile}</span>}
              </Descriptions.Item>
              {data.address && <Descriptions.Item label={t("invoices.fields.address")}>{data.address}</Descriptions.Item>}
              {data.notes && <Descriptions.Item label={t("companies.fields.notes")}>{data.notes}</Descriptions.Item>}
            </Descriptions>
          </Card>
        </Col>
        <Col xs={24} lg={14}>
          <Flex vertical gap={16}>
            <Card
              title={`${t("companies.departments")} (${formatNumber(data.departments?.length ?? 0)})`}
              extra={
                manage && (
                  <Flex gap={4}>
                    <Input size="small" value={departmentName} onChange={(e) => setDepartmentName(e.target.value)} placeholder={t("companies.newDepartment")} aria-label={t("companies.newDepartment")} maxLength={100} />
                    <Button
                      size="small"
                      type="primary"
                      icon={<PlusOutlined />}
                      disabled={departmentName.trim() === ""}
                      onClick={() => saveDepartment.mutate({ companyId: data.id, input: { name: departmentName.trim() } }, { onSuccess: () => setDepartmentName(""), onError: fail })}
                      aria-label={t("companies.addDepartment")}
                    />
                  </Flex>
                )
              }
              styles={{ body: { padding: 0 } }}
            >
              <Table<Department> rowKey="id" size="small" columns={departmentColumns} dataSource={data.departments ?? []} pagination={false} locale={{ emptyText: t("companies.noDepartments") }} />
            </Card>
            <Card
              title={`${t("companies.members")} (${formatNumber(data.members?.length ?? 0)})`}
              extra={
                manage && (
                  <Select
                    showSearch
                    filterOption={false}
                    onSearch={setSearch}
                    size="small"
                    value={null}
                    placeholder={
                      <span>
                        <UserAddOutlined /> {t("companies.addMember")}
                      </span>
                    }
                    aria-label={t("companies.addMember")}
                    loading={candidates.isFetching}
                    options={(candidates.data?.data ?? []).map((c) => ({ value: c.id, label: `${c.name} · ${c.mobile}` }))}
                    onChange={(customerId: number) => addMember.mutate({ companyId: data.id, customerId }, { onSuccess: () => setSearch(""), onError: fail })}
                    style={{ width: 240 }}
                  />
                )
              }
              styles={{ body: { padding: 0 } }}
            >
              <Typography.Paragraph type="secondary" style={{ margin: 12 }}>
                {t("companies.membersHelp")}
              </Typography.Paragraph>
              <Table<Member> rowKey="id" size="small" columns={memberColumns} dataSource={data.members ?? []} pagination={false} />
            </Card>
          </Flex>
        </Col>
        <Col xs={24}>
          <DeliveryNotesCard company={data} />
        </Col>
      </Row>
      <CompanyFormModal company={data} open={editing} onClose={() => setEditing(false)} />
    </>
  );
}
