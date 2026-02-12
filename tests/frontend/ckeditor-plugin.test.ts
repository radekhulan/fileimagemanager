import { describe, it, expect } from 'vitest'
import { createRfmBrowseAdapter } from '@/plugins/ckeditor'

describe('createRfmBrowseAdapter', () => {
  describe('buildUrl', () => {
    it('returns the default URL with editor and popup params', () => {
      const adapter = createRfmBrowseAdapter()
      const url = adapter.buildUrl()

      expect(url).toBe('/filemanager/?editor=ckeditor&popup=1')
    })

    it('uses a custom filemanagerUrl', () => {
      const adapter = createRfmBrowseAdapter({ filemanagerUrl: '/custom/path/' })
      const url = adapter.buildUrl()

      expect(url).toBe('/custom/path/?editor=ckeditor&popup=1')
    })

    it('adds akey param when accessKey is provided', () => {
      const adapter = createRfmBrowseAdapter({ accessKey: 'my-secret-key' })
      const url = adapter.buildUrl()

      expect(url).toContain('akey=my-secret-key')
      expect(url).toContain('editor=ckeditor')
      expect(url).toContain('popup=1')
      expect(url).toBe('/filemanager/?editor=ckeditor&popup=1&akey=my-secret-key')
    })

    it('adds crossdomain=1 param when crossdomain is true', () => {
      const adapter = createRfmBrowseAdapter({ crossdomain: true })
      const url = adapter.buildUrl()

      expect(url).toContain('crossdomain=1')
      expect(url).toBe('/filemanager/?editor=ckeditor&popup=1&crossdomain=1')
    })

    it('includes both akey and crossdomain when both are set', () => {
      const adapter = createRfmBrowseAdapter({
        accessKey: 'test-key',
        crossdomain: true,
      })
      const url = adapter.buildUrl()

      expect(url).toContain('akey=test-key')
      expect(url).toContain('crossdomain=1')
      expect(url).toContain('editor=ckeditor')
      expect(url).toContain('popup=1')
    })

    it('does not add akey param when accessKey is empty string', () => {
      const adapter = createRfmBrowseAdapter({ accessKey: '' })
      const url = adapter.buildUrl()

      expect(url).not.toContain('akey')
      expect(url).toBe('/filemanager/?editor=ckeditor&popup=1')
    })

    it('does not add crossdomain param when crossdomain is false', () => {
      const adapter = createRfmBrowseAdapter({ crossdomain: false })
      const url = adapter.buildUrl()

      expect(url).not.toContain('crossdomain')
      expect(url).toBe('/filemanager/?editor=ckeditor&popup=1')
    })

    it('encodes special characters in accessKey', () => {
      const adapter = createRfmBrowseAdapter({ accessKey: 'key with spaces&special=chars' })
      const url = adapter.buildUrl()

      // URLSearchParams auto-encodes
      expect(url).toContain('akey=key+with+spaces')
      expect(url).not.toContain('akey=key with spaces')
    })

    it('combines custom URL with all options', () => {
      const adapter = createRfmBrowseAdapter({
        filemanagerUrl: '/my-fm/',
        accessKey: 'abc123',
        crossdomain: true,
      })
      const url = adapter.buildUrl()

      expect(url).toBe('/my-fm/?editor=ckeditor&popup=1&akey=abc123&crossdomain=1')
    })
  })
})
