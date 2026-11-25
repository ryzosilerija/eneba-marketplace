import { useEffect, useState } from 'react'

/**
 * Returns `value`, but only after it has stopped changing for `delay` ms.
 * Used by the search box so we send one request when the user pauses typing,
 * instead of one request per keystroke.
 */
export function useDebounce<T>(value: T, delay = 300): T {
  const [debounced, setDebounced] = useState(value)
  useEffect(() => {
    const id = setTimeout(() => setDebounced(value), delay)
    return () => clearTimeout(id) // a new keystroke cancels the pending update
  }, [value, delay])
  return debounced
}
