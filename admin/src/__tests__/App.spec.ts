import { describe, it, expect } from 'vitest'

import { mount } from '@vue/test-utils'
import LoginView from '../views/auth/LoginView.vue'

describe('App', () => {
  it('renders the login page', () => {
    const wrapper = mount(LoginView, {
      global: {
        mocks: {
          $router: {
            push: () => undefined,
          },
        },
      },
    })

    expect(wrapper.text()).toContain('Вход')
  })
})
