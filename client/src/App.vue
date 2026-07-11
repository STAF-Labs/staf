<script setup lang="ts">
import { RouterView } from 'vue-router'
</script>

<template>
  <RouterView />
</template>

<style>
:root {
  color: #16201d;
  background: #f5f7f4;
  font-family:
    Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

* {
  box-sizing: border-box;
}

body {
  min-width: 320px;
  margin: 0;
}

button,
input {
  font: inherit;
}

button {
  min-height: 44px;
  border: 0;
  border-radius: 6px;
  padding: 0 18px;
  color: #ffffff;
  background: #1f6f5b;
  cursor: pointer;
}

button:disabled {
  cursor: not-allowed;
  opacity: 0.7;
}

input[type='email'],
input[type='password'] {
  width: 100%;
  min-height: 44px;
  border: 1px solid #b7c3be;
  border-radius: 6px;
  padding: 0 12px;
  color: #16201d;
  background: #ffffff;
}

input[aria-invalid='true'] {
  border-color: #b42318;
}

.auth-shell,
.dashboard-shell {
  min-height: 100vh;
  padding: 32px;
}

.auth-shell {
  display: grid;
  place-items: center;
}

.auth-panel {
  width: min(100%, 420px);
  border: 1px solid #d8dfdc;
  border-radius: 8px;
  padding: 28px;
  background: #ffffff;
  box-shadow: 0 20px 50px rgb(22 32 29 / 8%);
}

.auth-form {
  display: grid;
  gap: 18px;
  margin-top: 26px;
}

.auth-form label {
  display: grid;
  gap: 8px;
  font-size: 14px;
  font-weight: 650;
}

.check-row {
  display: flex !important;
  grid-template-columns: none;
  align-items: center;
  gap: 10px !important;
  font-weight: 500 !important;
}

.field-error,
.form-message {
  color: #b42318;
}

.field-error {
  font-size: 13px;
  font-weight: 500;
}

.form-message {
  margin: 0;
  font-size: 14px;
}

.dashboard-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  max-width: 960px;
  margin: 0 auto;
  border-bottom: 1px solid #d8dfdc;
  padding: 28px 0;
}

.eyebrow {
  margin: 0 0 8px;
  color: #55716a;
  font-size: 12px;
  font-weight: 750;
  text-transform: uppercase;
}

h1 {
  margin: 0;
  font-size: 32px;
  line-height: 1.15;
}

.muted {
  margin: 8px 0 0;
  color: #5f6f6a;
}

@media (max-width: 640px) {
  .auth-shell,
  .dashboard-shell {
    padding: 18px;
  }

  .auth-panel {
    padding: 22px;
  }

  .dashboard-header {
    align-items: stretch;
    flex-direction: column;
  }
}
</style>
