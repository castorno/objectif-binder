import { hueFromString } from '../../lib/hue'

type CardArtProps = {
  name: string
  setCode: string
  numberInSet: string
  className?: string
}

/**
 * Generated stand-in for a card picture. The project ships no third-party
 * artwork, so each card gets a deterministic gradient derived from its name.
 * Purely decorative: the accessible name comes from the surrounding text.
 */
export function CardArt({ name, setCode, numberInSet, className = '' }: CardArtProps) {
  const hue = hueFromString(name)

  return (
    <div
      aria-hidden="true"
      className={`relative aspect-[5/7] overflow-hidden rounded-[6%/4.3%] text-white shadow-card ${className}`}
      style={{
        background: `linear-gradient(155deg, hsl(${hue} 55% 34%), hsl(${(hue + 50) % 360} 60% 16%))`,
      }}
    >
      <div
        className="absolute inset-[6%] rounded-[5%/3.6%] border border-white/25"
        style={{
          background: `radial-gradient(circle at 30% 20%, hsl(${hue} 90% 75% / 0.45), transparent 55%)`,
        }}
      />
      <span className="absolute top-[9%] left-[11%] text-[0.7em] font-semibold tracking-widest uppercase opacity-80">
        {setCode}
      </span>
      <span className="absolute right-[11%] bottom-[8%] font-mono text-[1.6em] leading-none font-bold opacity-90">
        {numberInSet}
      </span>
    </div>
  )
}
