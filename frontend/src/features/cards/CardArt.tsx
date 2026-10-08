import { useState } from 'react'
import { hueFromString } from '../../lib/hue'

type CardArtProps = {
  name: string
  setCode: string
  numberInSet: string
  /** Address of a picture of the card, when the catalogue has one. */
  imageUrl?: string | null
  className?: string
}

/**
 * The picture of a card. The project ships no third-party artwork: a card
 * gets a generated stand-in, a deterministic gradient derived from its name,
 * unless the catalogue knows where someone else serves its picture.
 * Purely decorative either way: the accessible name comes from the
 * surrounding text.
 */
export function CardArt({ name, setCode, numberInSet, imageUrl = null, className = '' }: CardArtProps) {
  const hue = hueFromString(name)
  // The address that failed, not a mere flag: another card shown by this
  // same component must get its own chance.
  const [failedUrl, setFailedUrl] = useState<string | null>(null)
  const showPicture = imageUrl !== null && imageUrl !== failedUrl

  return (
    <div
      aria-hidden="true"
      className={`relative aspect-[5/7] overflow-hidden rounded-[6%/4.3%] text-white shadow-card ${className}`}
      style={{
        // Also what shows while a picture loads, and around its rounded corners.
        background: `linear-gradient(155deg, hsl(${hue} 55% 34%), hsl(${(hue + 50) % 360} 60% 16%))`,
      }}
    >
      {showPicture ? (
        <img
          src={imageUrl}
          alt=""
          // Only fetched when it comes near the screen: a page of cards
          // must not ask someone else's server for pictures nobody sees.
          loading="lazy"
          decoding="async"
          // The third party serving the picture does not need to know
          // which page of this site showed it.
          referrerPolicy="no-referrer"
          // Unreachable or gone: back to the generated stand-in.
          onError={() => setFailedUrl(imageUrl)}
          className="absolute inset-0 h-full w-full object-cover"
        />
      ) : (
        <>
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
        </>
      )}
    </div>
  )
}
