import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { Barcode } from "./Barcode";

describe("Barcode", () => {
  it("draws a Code 128 barcode as SVG", () => {
    render(<Barcode value="T1-00042-03" />);
    const barcode = screen.getByRole("img", { name: "T1-00042-03" });
    expect(barcode.querySelector("svg")).not.toBeNull();
    expect(barcode.querySelector("svg path")).not.toBeNull();
  });

  it("draws a QR code for links", () => {
    render(<Barcode value="https://example.test/track/01JABC" type="qrcode" />);
    expect(screen.getByRole("img").querySelector("svg")).not.toBeNull();
  });
});
