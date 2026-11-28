"use client";

import { DeleteOutlined, EditOutlined, EnvironmentOutlined } from "@ant-design/icons";
import { Alert, App, Button, Card, Col, Descriptions, Empty, List, Popconfirm, Result, Row, Skeleton, Tag } from "antd";
import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { useState } from "react";
import { errorMessage } from "@/shared/api/formErrors";
import { isApiError } from "@/shared/api/client";
import { usePermission } from "@/shared/hooks/usePermission";
import { formatDate } from "@/shared/lib/date";
import { PageHeader } from "@/shared/ui/PageHeader";
import { useCustomer, useDeleteCustomer } from "../api";
import { CustomerFormModal } from "./CustomerFormModal";

export function CustomerDetailPage() {
  const t = useTranslations();
  const { message } = App.useApp();
  const router = useRouter();
  const { can } = usePermission();
  const id = Number(useParams<{ id: string }>().id);
  const customer = useCustomer(Number.isFinite(id) ? id : null);
  const remove = useDeleteCustomer();
  const [editing, setEditing] = useState(false);

  if (customer.isLoading) {
    return <Skeleton active />;
  }
  if (customer.isError || !customer.data) {
    return (
      <Result
        status={isApiError(customer.error, 404) ? "404" : "error"}
        title={isApiError(customer.error, 404) ? t("common.notFoundTitle") : t("common.error")}
        extra={<Link href="/customers">{t("customers.backToList")}</Link>}
      />
    );
  }

  const data = customer.data;

  return (
    <>
      <PageHeader
        title={data.name}
        subtitle={<span className="ltr">{data.mobile}</span>}
        extra={
          <>
            {can("customers.manage") && (
              <Button icon={<EditOutlined />} onClick={() => setEditing(true)}>
                {t("common.edit")}
              </Button>
            )}
            {can("customers.delete") && (
              <Popconfirm
                title={t("customers.deleteConfirm", { name: data.name })}
                okText={t("common.delete")}
                cancelText={t("common.cancel")}
                okButtonProps={{ danger: true }}
                onConfirm={() =>
                  remove.mutateAsync(data.id).then(
                    () => {
                      message.success(t("common.deleted"));
                      router.replace("/customers");
                    },
                    (error: unknown) => message.error(errorMessage(error, t("common.error"))),
                  )
                }
              >
                <Button danger icon={<DeleteOutlined />}>
                  {t("common.delete")}
                </Button>
              </Popconfirm>
            )}
          </>
        }
      />
      {data.alert && <Alert type="warning" showIcon title={data.alert} style={{ marginBottom: 16 }} />}
      <Row gutter={[16, 16]}>
        <Col xs={24} lg={10}>
          <Card title={t("customers.profile")}>
            <Descriptions column={1} size="small">
              <Descriptions.Item label={t("customers.fields.mobile")}>
                <a className="ltr" href={`tel:${data.mobile}`}>
                  {data.mobile}
                </a>
              </Descriptions.Item>
              {data.phone && (
                <Descriptions.Item label={t("customers.fields.phone")}>
                  <span className="ltr">{data.phone}</span>
                </Descriptions.Item>
              )}
              {data.company && (
                <Descriptions.Item label={t("orders.detail.company")}>
                  <Link href={`/companies/${data.company.id}`}>{data.company.name}</Link>
                </Descriptions.Item>
              )}
              <Descriptions.Item label={t("customers.fields.createdAt")}>{formatDate(data.created_at)}</Descriptions.Item>
              {data.notes && <Descriptions.Item label={t("customers.fields.notes")}>{data.notes}</Descriptions.Item>}
            </Descriptions>
          </Card>
        </Col>
        <Col xs={24} lg={14}>
          <Card title={t("customers.addresses")}>
            {(data.addresses ?? []).length === 0 ? (
              <Empty description={t("customers.noAddresses")} />
            ) : (
              <List
                dataSource={data.addresses ?? []}
                renderItem={(address) => (
                  <List.Item>
                    <List.Item.Meta
                      avatar={<EnvironmentOutlined />}
                      title={
                        <>
                          {address.title ?? t("customers.fields.address")} {address.is_default && <Tag color="blue">{t("customers.fields.default")}</Tag>}
                        </>
                      }
                      description={address.address}
                    />
                  </List.Item>
                )}
              />
            )}
          </Card>
        </Col>
      </Row>
      <CustomerFormModal open={editing} customer={data} onClose={() => setEditing(false)} />
    </>
  );
}
