import { Button, Result } from "antd";
import Link from "next/link";

export default function NotFound() {
  return (
    <Result
      status="404"
      title="۴۰۴"
      subTitle="صفحه پیدا نشد."
      extra={
        <Link href="/dashboard">
          <Button type="primary">صفحه اصلی</Button>
        </Link>
      }
    />
  );
}
