import { describe, expect, it } from "vitest";
import { normalizeCode, safeNextPath } from "./logic";

describe("normalizeCode", () => {
  it.each([["1", "0001"], ["0016", "0016"], ["ADM-000A", "ADM-000A"], ["10000", "10000"]])("normaliza %s", (input, expected) => expect(normalizeCode(input)).toBe(expected));
  it.each(["", "0", "1a", "-1", "ADM-123"])("rejeita %s", (input) => expect(normalizeCode(input)).toBeNull());
});

describe("safeNextPath", () => {
  it("aceita somente caminhos internos", () => {
    expect(safeNextPath("/register/0016")).toBe("/register/0016");
    expect(safeNextPath("//evil.example")).toBe("/");
    expect(safeNextPath("https://evil.example")).toBe("/");
  });
});
