import { useEffect, useRef } from 'react'

/** Calls `callback(value)` once `value` has stopped changing for `delayMs`. */
export function useDebouncedEffect<T>(value: T, delayMs: number, callback: (value: T) => void): void {
  const callbackRef = useRef(callback)
  useEffect(() => {
    callbackRef.current = callback
  })

  useEffect(() => {
    const timeout = setTimeout(() => callbackRef.current(value), delayMs)

    return () => clearTimeout(timeout)
  }, [value, delayMs])
}
