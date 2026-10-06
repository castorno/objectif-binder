export function RarityBadge({ rarity }: { rarity: string | null }) {
  if (rarity === null) return null

  return (
    <span className="inline-flex items-center rounded-full bg-accent-soft px-2 py-0.5 text-xs font-medium">
      {rarity}
    </span>
  )
}
