const euro = new Intl.NumberFormat('en-IE', { style: 'currency', currency: 'EUR' })

/** 3949 → "€39.49" (the API sends integer cents). */
export const formatPrice = (cents: number | null | undefined): string =>
  cents === null || cents === undefined ? '—' : euro.format(cents / 100)

export const formatDate = (iso: string | null | undefined): string =>
  iso ? new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'

/** "NORTH AMERICA" → "North America" */
export const titleCase = (s: string): string => s.toLowerCase().replace(/\b\w/g, (c) => c.toUpperCase())
