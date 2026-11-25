"use client";

import { Input, type InputRef } from "antd";
import { useRef, useState } from "react";
import { splitTags } from "../tags";

/**
 * A scan field for lists: each scan (or pasted batch) is handed over as
 * tags, then the field clears and keeps focus for the next one.
 */
export function ScanField({ label, placeholder, onTags, disabled }: { label: string; placeholder: string; onTags: (tags: string[]) => void; disabled?: boolean }) {
  const ref = useRef<InputRef>(null);
  const [text, setText] = useState("");
  return (
    <Input
      ref={ref}
      size="large"
      aria-label={label}
      placeholder={placeholder}
      value={text}
      disabled={disabled}
      onChange={(e) => setText(e.target.value)}
      onPressEnter={() => {
        const tags = splitTags(text);
        setText("");
        if (tags.length > 0) onTags(tags);
        setTimeout(() => ref.current?.focus(), 0);
      }}
      className="ltr"
      style={{ textAlign: "center" }}
    />
  );
}
