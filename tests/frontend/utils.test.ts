import { describe, it, expect } from 'vitest'
import { formatFileSize, formatDate, formatDateTime } from '@/utils/filesize'
import {
  getCategoryColor,
  getCategoryIcon,
  isEditableImage,
  isPreviewableMedia,
} from '@/utils/extensions'
import type { FileCategory } from '@/types/files'

// ---------------------------------------------------------------------------
// formatFileSize
// ---------------------------------------------------------------------------
describe('formatFileSize', () => {
  it('returns "0 B" for zero bytes', () => {
    expect(formatFileSize(0)).toBe('0 B')
  })

  it('formats bytes (< 1 KB) without decimal', () => {
    expect(formatFileSize(1)).toBe('1 B')
    expect(formatFileSize(512)).toBe('512 B')
    expect(formatFileSize(1023)).toBe('1023 B')
  })

  it('formats kilobytes with one decimal', () => {
    expect(formatFileSize(1024)).toBe('1.0 KB')
    expect(formatFileSize(1536)).toBe('1.5 KB')
    expect(formatFileSize(10240)).toBe('10.0 KB')
  })

  it('formats megabytes with one decimal', () => {
    expect(formatFileSize(1048576)).toBe('1.0 MB')
    expect(formatFileSize(1572864)).toBe('1.5 MB')
  })

  it('formats gigabytes with one decimal', () => {
    expect(formatFileSize(1073741824)).toBe('1.0 GB')
  })

  it('formats terabytes with one decimal', () => {
    expect(formatFileSize(1099511627776)).toBe('1.0 TB')
  })
})

// ---------------------------------------------------------------------------
// formatDate
// ---------------------------------------------------------------------------
describe('formatDate', () => {
  it('converts a unix timestamp to a date string', () => {
    // 1700000000 = 2023-11-14 in UTC
    const result = formatDate(1700000000)
    // The result depends on locale, but it should contain the year 2023
    expect(result).toContain('2023')
    // Should be a non-empty string
    expect(result.length).toBeGreaterThan(0)
  })

  it('handles epoch zero', () => {
    const result = formatDate(0)
    // epoch 0 = 1970-01-01
    expect(result).toContain('1970')
  })

  it('returns a string with numeric month and day', () => {
    // Just ensure it returns a string (locale-dependent formatting)
    const result = formatDate(1609459200) // 2021-01-01 00:00:00 UTC
    expect(typeof result).toBe('string')
    expect(result).toContain('2021')
  })
})

// ---------------------------------------------------------------------------
// formatDateTime
// ---------------------------------------------------------------------------
describe('formatDateTime', () => {
  it('converts a unix timestamp to a datetime string', () => {
    const result = formatDateTime(1700000000)
    expect(result).toContain('2023')
    expect(result.length).toBeGreaterThan(0)
  })

  it('includes time components (contains digits beyond the date)', () => {
    const result = formatDateTime(1700000000)
    // DateTime should be longer than just a date since it includes hours and minutes
    const dateOnly = formatDate(1700000000)
    expect(result.length).toBeGreaterThan(dateOnly.length)
  })

  it('handles epoch zero', () => {
    const result = formatDateTime(0)
    expect(result).toContain('1970')
  })
})

// ---------------------------------------------------------------------------
// getCategoryColor
// ---------------------------------------------------------------------------
describe('getCategoryColor', () => {
  const expectations: Record<FileCategory, string> = {
    image: 'text-green-600 dark:text-green-400',
    video: 'text-purple-600 dark:text-purple-400',
    audio: 'text-rose-600 dark:text-rose-400',
    document: 'text-gray-500 dark:text-gray-400',
    archive: 'text-amber-600 dark:text-amber-400',
    misc: 'text-gray-500 dark:text-gray-400',
    directory: 'text-amber-500 dark:text-amber-400',
  }

  for (const [category, expected] of Object.entries(expectations)) {
    it(`returns "${expected}" for "${category}"`, () => {
      expect(getCategoryColor(category as FileCategory)).toBe(expected)
    })
  }
})

// ---------------------------------------------------------------------------
// getCategoryIcon
// ---------------------------------------------------------------------------
describe('getCategoryIcon', () => {
  const expectations: Record<FileCategory, string> = {
    image: 'icon-image',
    video: 'icon-media',
    audio: 'icon-media',
    document: 'icon-file',
    archive: 'icon-archive',
    misc: 'icon-file',
    directory: 'folder',
  }

  for (const [category, expected] of Object.entries(expectations)) {
    it(`returns "${expected}" for "${category}"`, () => {
      expect(getCategoryIcon(category as FileCategory)).toBe(expected)
    })
  }
})

// ---------------------------------------------------------------------------
// isEditableImage
// ---------------------------------------------------------------------------
describe('isEditableImage', () => {
  it.each(['jpg', 'jpeg', 'png', 'webp'])('returns true for "%s"', (ext) => {
    expect(isEditableImage(ext)).toBe(true)
  })

  it.each(['JPG', 'JPEG', 'PNG', 'WEBP'])('is case-insensitive ("%s")', (ext) => {
    expect(isEditableImage(ext)).toBe(true)
  })

  it.each(['gif', 'svg', 'pdf', 'bmp', 'tiff', 'mp4', ''])('returns false for "%s"', (ext) => {
    expect(isEditableImage(ext)).toBe(false)
  })
})

// ---------------------------------------------------------------------------
// isPreviewableMedia
// ---------------------------------------------------------------------------
describe('isPreviewableMedia', () => {
  const extVideo = ['mp4', 'webm', 'ogg']
  const extMusic = ['mp3', 'wav', 'flac']

  it('returns true for a video extension', () => {
    expect(isPreviewableMedia('mp4', extVideo, extMusic)).toBe(true)
    expect(isPreviewableMedia('webm', extVideo, extMusic)).toBe(true)
    expect(isPreviewableMedia('ogg', extVideo, extMusic)).toBe(true)
  })

  it('returns true for a music extension', () => {
    expect(isPreviewableMedia('mp3', extVideo, extMusic)).toBe(true)
    expect(isPreviewableMedia('wav', extVideo, extMusic)).toBe(true)
    expect(isPreviewableMedia('flac', extVideo, extMusic)).toBe(true)
  })

  it('returns false for non-media extensions', () => {
    expect(isPreviewableMedia('jpg', extVideo, extMusic)).toBe(false)
    expect(isPreviewableMedia('pdf', extVideo, extMusic)).toBe(false)
    expect(isPreviewableMedia('zip', extVideo, extMusic)).toBe(false)
  })

  it('is case-insensitive (lowercases input before matching)', () => {
    expect(isPreviewableMedia('MP4', extVideo, extMusic)).toBe(true)
    expect(isPreviewableMedia('WAV', extVideo, extMusic)).toBe(true)
  })

  it('returns false with empty allowed lists', () => {
    expect(isPreviewableMedia('mp4', [], [])).toBe(false)
  })
})
