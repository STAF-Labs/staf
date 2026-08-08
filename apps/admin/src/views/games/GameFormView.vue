<script setup lang="ts">
import { ArrowLeft, Bold, Heading2, Image as ImageIcon, Italic, List, ListOrdered, Pencil, Save, Trash2 } from '@lucide/vue'
import StarterKit from '@tiptap/starter-kit'
import { EditorContent, useEditor } from '@tiptap/vue-3'
import { computed, onBeforeUnmount, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppShell from '@/components/layout/AppShell.vue'
import { createGame, fetchGame, updateGame, type GameStatus } from '@/shared/games/games'
import '@/assets/styles/game-form.css'

type GameForm = {
  name: string
  releasedAt: string
  status: GameStatus
  logo: File | null
  banner: File | null
}

type ImageField = 'banner' | 'logo'

type ImageLimits = {
  maxBytes: number
  maxKilobytes: number
  maxWidth: number
  maxHeight: number
  required: boolean
}

const imageMimeTypes = ['image/jpeg', 'image/png', 'image/webp']
const imageAccept = imageMimeTypes.join(',')
const imageTypeLabel = 'JPG, PNG, WEBP'
const imageLimits: Record<ImageField, ImageLimits> = {
  banner: {
    maxBytes: 4 * 1024 * 1024,
    maxKilobytes: 4096,
    maxWidth: 2560,
    maxHeight: 960,
    required: true,
  },
  logo: {
    maxBytes: 2 * 1024 * 1024,
    maxKilobytes: 2048,
    maxWidth: 1024,
    maxHeight: 1024,
    required: false,
  },
}

const route = useRoute()
const router = useRouter()
const isSubmitting = ref(false)
const isLoading = ref(false)
const message = ref('')
const messageType = ref<'success' | 'error'>('error')
const logoInput = ref<HTMLInputElement | null>(null)
const bannerInput = ref<HTMLInputElement | null>(null)
const currentLogoUrl = ref<string | null>(null)
const currentBannerUrl = ref<string | null>(null)
const logoPreviewUrl = ref<string | null>(null)
const bannerPreviewUrl = ref<string | null>(null)
const fileErrors = reactive<Record<ImageField, string>>({
  banner: '',
  logo: '',
})

const isEditing = computed(() => route.name === 'games.edit')
const gameId = computed(() => String(route.params.id ?? ''))
const pageTitle = computed(() => (isEditing.value ? 'Редактирование игры' : 'Создание игры'))
const subtitle = computed(() => (
  isEditing.value ? `ID игры: ${gameId.value}` : 'Новая игра появится в списке после сохранения.'
))

route.meta.breadcrumbLabel = pageTitle.value

const form = reactive<GameForm>({
  name: '',
  releasedAt: '',
  status: 'active',
  logo: null,
  banner: null,
})

const editor = useEditor({
  extensions: [StarterKit],
  content: '',
})

const bannerHelpText = computed(() => (
  `Максимальный размер ${formatMegabytes(imageLimits.banner.maxBytes)}, до ${imageLimits.banner.maxWidth}x${imageLimits.banner.maxHeight}px. Типы: ${imageTypeLabel}.`
))

const logoHelpText = computed(() => (
  `Максимальный размер ${formatMegabytes(imageLimits.logo.maxBytes)}, до ${imageLimits.logo.maxWidth}x${imageLimits.logo.maxHeight}px. Типы: ${imageTypeLabel}.`
))

function formatMegabytes(bytes: number): string {
  return `${bytes / 1024 / 1024} МБ`
}

async function chooseFile(target: ImageField, event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const [file] = Array.from(input.files ?? [])

  fileErrors[target] = ''

  if (!file) {
    form[target] = null

    return
  }

  const error = await validateImageFile(file, imageLimits[target])

  if (error) {
    form[target] = null
    fileErrors[target] = error
    input.value = ''

    return
  }

  form[target] = file
  setPreviewUrl(target, URL.createObjectURL(file))
}

function clearFile(target: ImageField): void {
  form[target] = null
  fileErrors[target] = ''
  setPreviewUrl(target, null)

  if (target === 'logo' && logoInput.value) {
    logoInput.value.value = ''
  }

  if (target === 'banner' && bannerInput.value) {
    bannerInput.value.value = ''
  }
}

function openFilePicker(target: ImageField): void {
  if (target === 'banner') {
    bannerInput.value?.click()

    return
  }

  logoInput.value?.click()
}

function removeFile(target: ImageField): void {
  clearFile(target)

  if (target === 'banner') {
    currentBannerUrl.value = null

    return
  }

  currentLogoUrl.value = null
}

function setPreviewUrl(target: ImageField, url: string | null): void {
  const preview = target === 'banner' ? bannerPreviewUrl : logoPreviewUrl

  if (preview.value) {
    URL.revokeObjectURL(preview.value)
  }

  preview.value = url
}

async function validateImageFile(file: File, limits: ImageLimits): Promise<string> {
  if (!imageMimeTypes.includes(file.type)) {
    return `Доступные типы файлов: ${imageTypeLabel}.`
  }

  if (file.size > limits.maxBytes) {
    return `Файл должен быть не больше ${formatMegabytes(limits.maxBytes)}.`
  }

  const dimensions = await getImageDimensions(file)

  if (dimensions.width > limits.maxWidth || dimensions.height > limits.maxHeight) {
    return `Изображение должно быть не больше ${limits.maxWidth}x${limits.maxHeight}px.`
  }

  return ''
}

function getImageDimensions(file: File): Promise<{ width: number, height: number }> {
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file)
    const image = new window.Image()

    image.onload = () => {
      URL.revokeObjectURL(url)
      resolve({
        width: image.naturalWidth,
        height: image.naturalHeight,
      })
    }

    image.onerror = () => {
      URL.revokeObjectURL(url)
      reject(new Error('Invalid image'))
    }

    image.src = url
  })
}

function validateForm(): boolean {
  fileErrors.banner = ''
  fileErrors.logo = ''

  if (!isEditing.value && !form.banner) {
    fileErrors.banner = 'Баннер обязателен.'
  }

  return !fileErrors.banner && !fileErrors.logo
}

function setEditorContent(value: unknown): void {
  if (!editor.value) {
    return
  }

  if (value && typeof value === 'object') {
    editor.value.commands.setContent(value)

    return
  }

  editor.value.commands.clearContent()
}

async function loadGame(): Promise<void> {
  if (!isEditing.value) {
    return
  }

  isLoading.value = true
  message.value = ''

  try {
    const game = await fetchGame(gameId.value)

    form.name = game.name
    form.releasedAt = game.released_at ?? ''
    form.status = game.status ?? 'active'
    currentLogoUrl.value = game.logo_url
    currentBannerUrl.value = game.banner_url
    route.meta.breadcrumbLabel = game.name
    setEditorContent(game.description)
  } catch {
    messageType.value = 'error'
    message.value = 'Не удалось загрузить игру.'
  } finally {
    isLoading.value = false
  }
}

async function submit(): Promise<void> {
  if (!validateForm()) {
    return
  }

  if (!isEditing.value && !form.banner) {
    return
  }

  isSubmitting.value = true
  message.value = ''
  messageType.value = 'error'

  try {
    const payload = {
      name: form.name,
      description: editor.value?.getJSON() ?? null,
      releasedAt: form.releasedAt,
      status: form.status,
      logo: form.logo,
      banner: form.banner,
    }

    if (isEditing.value) {
      const game = await updateGame(gameId.value, payload)

      currentLogoUrl.value = game.logo_url
      currentBannerUrl.value = game.banner_url
      route.meta.breadcrumbLabel = game.name
      setPreviewUrl('logo', null)
      setPreviewUrl('banner', null)
      messageType.value = 'success'
      message.value = 'Игра сохранена.'

      return
    }

    await createGame(payload)

    await router.push({ name: 'games.index' })
  } catch {
    messageType.value = 'error'
    message.value = 'Не удалось сохранить игру.'
  } finally {
    isSubmitting.value = false
  }
}

onBeforeUnmount(() => {
  if (bannerPreviewUrl.value) {
    URL.revokeObjectURL(bannerPreviewUrl.value)
  }

  if (logoPreviewUrl.value) {
    URL.revokeObjectURL(logoPreviewUrl.value)
  }

  editor.value?.destroy()
})

void loadGame()
</script>

<template>
  <AppShell>
    <section class="data-page">
      <header class="data-page__header game-form-header">
        <RouterLink
          class="data-page__back-link game-form-header__back"
          :to="{ name: 'games.index' }"
          aria-label="Назад к играм"
        >
          <ArrowLeft :size="18" :stroke-width="1.9" aria-hidden="true" />
          <span>Игры</span>
        </RouterLink>

        <h2 class="data-page__title">{{ pageTitle }}</h2>
        <p class="data-page__subtitle">{{ subtitle }}</p>
      </header>

      <form class="game-form-panel form" @submit.prevent="submit">
        <p v-if="message" class="form-message game-form-message" :class="`game-form-message--${messageType}`">
          {{ message }}
        </p>
        <p v-if="isLoading" class="game-form-loading">Загрузка...</p>

        <div
          class="game-upload game-upload--banner"
          :class="{
            'game-upload--invalid': fileErrors.banner,
            'game-upload--empty': !bannerPreviewUrl && !currentBannerUrl,
          }"
          @click="!bannerPreviewUrl && !currentBannerUrl ? openFilePicker('banner') : undefined"
        >
          <input
            ref="bannerInput"
            type="file"
            :accept="imageAccept"
            :required="!isEditing"
            @change="chooseFile('banner', $event)"
          >
          <img
            v-if="bannerPreviewUrl || currentBannerUrl"
            class="game-upload__preview"
            :src="bannerPreviewUrl ?? currentBannerUrl ?? ''"
            alt=""
          >
          <span v-if="!bannerPreviewUrl && !currentBannerUrl" class="game-upload__icon">
            <ImageIcon :size="30" :stroke-width="1.9" aria-hidden="true" />
          </span>
          <span v-if="!bannerPreviewUrl && !currentBannerUrl" class="game-upload__content">
            <span class="game-upload__title">Баннер</span>
            <span class="game-upload__file">
              {{ form.banner?.name ?? 'Выберите изображение' }}
            </span>
          </span>
          <div v-if="bannerPreviewUrl || currentBannerUrl" class="game-upload__actions">
            <button
              class="game-upload__action icon-action"
              type="button"
              aria-label="Заменить баннер"
              title="Заменить баннер"
              @click="openFilePicker('banner')"
            >
              <Pencil :size="17" :stroke-width="2" aria-hidden="true" />
            </button>

            <button
              v-if="form.banner || currentBannerUrl"
              class="game-upload__action icon-action icon-action--danger"
              type="button"
              aria-label="Удалить баннер"
              title="Удалить баннер"
              @click="removeFile('banner')"
            >
              <Trash2 :size="17" :stroke-width="2" aria-hidden="true" />
            </button>
          </div>
        </div>

        <p class="game-form-help" :class="{ 'game-form-help--error': fileErrors.banner }">
          {{ fileErrors.banner || bannerHelpText }}
        </p>

        <div class="game-form-main-row">
          <div>
            <div
              class="game-upload game-upload--logo"
              :class="{
                'game-upload--invalid': fileErrors.logo,
                'game-upload--empty': !logoPreviewUrl && !currentLogoUrl,
              }"
              @click="!logoPreviewUrl && !currentLogoUrl ? openFilePicker('logo') : undefined"
            >
              <input ref="logoInput" type="file" :accept="imageAccept" @change="chooseFile('logo', $event)">
              <img
                v-if="logoPreviewUrl || currentLogoUrl"
                class="game-upload__preview"
                :src="logoPreviewUrl ?? currentLogoUrl ?? ''"
                alt=""
              >
              <span v-if="!logoPreviewUrl && !currentLogoUrl" class="game-upload__icon">
                <ImageIcon :size="24" :stroke-width="1.9" aria-hidden="true" />
              </span>
              <span v-if="!logoPreviewUrl && !currentLogoUrl" class="game-upload__content">
                <span class="game-upload__title">Логотип</span>
                <span class="game-upload__file">
                  {{ form.logo?.name ?? 'Файл не выбран' }}
                </span>
              </span>
              <div v-if="logoPreviewUrl || currentLogoUrl" class="game-upload__actions">
                <button
                  class="game-upload__action icon-action"
                  type="button"
                  aria-label="Заменить логотип"
                  title="Заменить логотип"
                  @click="openFilePicker('logo')"
                >
                  <Pencil :size="17" :stroke-width="2" aria-hidden="true" />
                </button>

                <button
                  v-if="form.logo || currentLogoUrl"
                  class="game-upload__action icon-action icon-action--danger"
                  type="button"
                  aria-label="Удалить логотип"
                  title="Удалить логотип"
                  @click="removeFile('logo')"
                >
                  <Trash2 :size="17" :stroke-width="2" aria-hidden="true" />
                </button>
              </div>
            </div>

            <p class="game-form-help" :class="{ 'game-form-help--error': fileErrors.logo }">
              {{ fileErrors.logo || logoHelpText }}
            </p>
          </div>

          <label class="form-field">
            <span class="form-label">Название</span>
            <input
              v-model.trim="form.name"
              class="form-control"
              type="text"
              maxlength="64"
              required
              placeholder="Например, S.T.A.L.K.E.R. 2"
            >
          </label>
        </div>

        <div class="game-form-meta-row">
          <label class="form-field">
            <span class="form-label">Дата релиза</span>
            <input v-model="form.releasedAt" class="form-control" type="date">
          </label>

          <label class="form-field">
            <span class="form-label">Статус</span>
            <select v-model="form.status" class="form-control">
              <option value="active">Активна</option>
              <option value="suspended">Приостановлена</option>
              <option value="blocked">Заблокирована</option>
            </select>
          </label>
        </div>

        <section class="form-field">
          <span class="form-label">Описание</span>

          <div class="game-editor">
            <div v-if="editor" class="game-editor__toolbar">
              <button
                class="game-editor__button"
                :class="{ 'is-active': editor.isActive('heading', { level: 2 }) }"
                type="button"
                title="Заголовок"
                @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
              >
                <Heading2 :size="17" :stroke-width="1.9" aria-hidden="true" />
              </button>

              <button
                class="game-editor__button"
                :class="{ 'is-active': editor.isActive('bold') }"
                type="button"
                title="Жирный"
                @click="editor.chain().focus().toggleBold().run()"
              >
                <Bold :size="17" :stroke-width="2.2" aria-hidden="true" />
              </button>

              <button
                class="game-editor__button"
                :class="{ 'is-active': editor.isActive('italic') }"
                type="button"
                title="Курсив"
                @click="editor.chain().focus().toggleItalic().run()"
              >
                <Italic :size="17" :stroke-width="2.2" aria-hidden="true" />
              </button>

              <button
                class="game-editor__button"
                :class="{ 'is-active': editor.isActive('bulletList') }"
                type="button"
                title="Маркированный список"
                @click="editor.chain().focus().toggleBulletList().run()"
              >
                <List :size="17" :stroke-width="1.9" aria-hidden="true" />
              </button>

              <button
                class="game-editor__button"
                :class="{ 'is-active': editor.isActive('orderedList') }"
                type="button"
                title="Нумерованный список"
                @click="editor.chain().focus().toggleOrderedList().run()"
              >
                <ListOrdered :size="17" :stroke-width="1.9" aria-hidden="true" />
              </button>
            </div>

            <EditorContent class="game-editor__body" :editor="editor" />
          </div>
        </section>

        <div class="game-form-actions">
          <RouterLink class="button" :to="{ name: 'games.index' }">
            Отмена
          </RouterLink>

          <button class="button button-primary game-form-submit" type="submit" :disabled="isSubmitting">
            <Save :size="18" :stroke-width="1.9" aria-hidden="true" />
            <span>{{ isSubmitting ? 'Сохранение...' : 'Сохранить' }}</span>
          </button>
        </div>
      </form>
    </section>
  </AppShell>
</template>
