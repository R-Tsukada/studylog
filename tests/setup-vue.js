/**
 * Vue.js テスト用セットアップファイル
 */
import * as Vue from 'vue'
import * as VueCompilerDOM from '@vue/compiler-dom'
import * as VueServerRenderer from '@vue/server-renderer'

// Vueをグローバルに設定
global.Vue = Vue
global.VueCompilerDOM = VueCompilerDOM
global.VueServerRenderer = VueServerRenderer

// JSDOMの設定
Object.defineProperty(window, 'matchMedia', {
  writable: true,
  value: jest.fn().mockImplementation(query => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: jest.fn(), // deprecated
    removeListener: jest.fn(), // deprecated
    addEventListener: jest.fn(),
    removeEventListener: jest.fn(),
    dispatchEvent: jest.fn(),
  })),
})