"use client";

import { CheckCircleFilled, CloseCircleFilled } from "@ant-design/icons";
import { Flex, Typography, theme } from "antd";
import type { TagResult } from "@/shared/api/types";

/** What happened to each scanned tag, refusals first. */
export function TagResults({ results }: { results: TagResult[] }) {
  const { token } = theme.useToken();
  if (results.length === 0) return null;
  const sorted = [...results].sort((a, b) => Number(a.ok) - Number(b.ok));
  return (
    <Flex vertical gap={4} data-testid="tag-results">
      {sorted.map((result) => (
        <Flex key={result.tag} gap={8} align="center">
          {result.ok ? <CheckCircleFilled style={{ color: token.colorSuccess }} /> : <CloseCircleFilled style={{ color: token.colorError }} />}
          <Typography.Text className="ltr" strong>
            {result.tag}
          </Typography.Text>
          {result.message && <Typography.Text type="danger">{result.message}</Typography.Text>}
        </Flex>
      ))}
    </Flex>
  );
}
