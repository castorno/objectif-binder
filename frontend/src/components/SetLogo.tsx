import { useState } from 'react'

/**
 * The picture that stands for a set: its logo, or the one an administrator
 * sent. Decorative: the name of the set is always written next to it.
 * Shows nothing for a set without picture, or when the picture cannot be
 * loaded.
 */
export function SetLogo({ url, className = '' }: { url: string | null; className?: string }) {
  // The address that failed, not a mere flag: another set shown by this
  // same component must get its own chance.
  const [failedUrl, setFailedUrl] = useState<string | null>(null)

  if (url === null || url === failedUrl) return null

  return (
    <img
      src={url}
      alt=""
      loading="lazy"
      decoding="async"
      // The third party serving the picture does not need to know which page showed it.
      referrerPolicy="no-referrer"
      onError={() => setFailedUrl(url)}
      className={`object-contain ${className}`}
    />
  )
}
