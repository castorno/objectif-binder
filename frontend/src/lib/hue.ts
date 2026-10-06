/** Deterministic hue (0-359) from a string, so a given card always gets the same placeholder colors. */
export function hueFromString(value: string): number {
  let hash = 0
  for (let i = 0; i < value.length; i++) {
    hash = (hash * 31 + value.charCodeAt(i)) >>> 0
  }

  return hash % 360
}
