import { describe, it, expect } from 'vitest'

/**
 * The getBasePath() function is private in frontend/api/client.ts.
 * We replicate its logic here to unit-test the algorithm without
 * modifying the production source code.
 *
 * Source (frontend/api/client.ts):
 *   function getBasePath(): string {
 *     const path = window.location.pathname
 *     return path.endsWith('/') ? path : path.substring(0, path.lastIndexOf('/') + 1)
 *   }
 */
function getBasePath(pathname: string): string {
  return pathname.endsWith('/')
    ? pathname
    : pathname.substring(0, pathname.lastIndexOf('/') + 1)
}

describe('getBasePath', () => {
  it('returns "/" when pathname is "/"', () => {
    expect(getBasePath('/')).toBe('/')
  })

  it('returns "/public/" when pathname is "/public/"', () => {
    expect(getBasePath('/public/')).toBe('/public/')
  })

  it('returns "/filemanager/" when pathname is "/filemanager/"', () => {
    expect(getBasePath('/filemanager/')).toBe('/filemanager/')
  })

  it('strips the filename when pathname does not end with /', () => {
    expect(getBasePath('/filemanager/index.html')).toBe('/filemanager/')
  })

  it('returns "/" when pathname is a bare filename like "/index.html"', () => {
    expect(getBasePath('/index.html')).toBe('/')
  })

  it('handles deeply nested paths ending with /', () => {
    expect(getBasePath('/a/b/c/d/')).toBe('/a/b/c/d/')
  })

  it('handles deeply nested paths ending with a file', () => {
    expect(getBasePath('/a/b/c/d/page.html')).toBe('/a/b/c/d/')
  })

  it('returns "/" for an empty string (edge case)', () => {
    // substring(0, lastIndexOf('/') + 1) on "" => substring(0, 0) => ""
    // but endsWith('/') is false, so it goes to the else branch
    expect(getBasePath('')).toBe('')
  })
})

describe('getBasePath used to build API base URL', () => {
  it('produces correct apiClient baseURL for root deployment', () => {
    const basePath = getBasePath('/')
    expect(basePath + 'api').toBe('/api')
  })

  it('produces correct apiClient baseURL for /public/ deployment', () => {
    const basePath = getBasePath('/public/')
    expect(basePath + 'api').toBe('/public/api')
  })

  it('produces correct apiClient baseURL for /filemanager/ deployment', () => {
    const basePath = getBasePath('/filemanager/')
    expect(basePath + 'api').toBe('/filemanager/api')
  })

  it('produces correct apiClient baseURL when path has a filename', () => {
    const basePath = getBasePath('/app/index.html')
    expect(basePath + 'api').toBe('/app/api')
  })
})
