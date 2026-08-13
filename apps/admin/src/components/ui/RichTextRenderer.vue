<script setup lang="ts">
import { computed, h, type VNodeChild } from 'vue'

type RichTextNode = {
  type?: string
  text?: string
  attrs?: Record<string, unknown>
  marks?: Array<{ type?: string; attrs?: Record<string, unknown> }>
  content?: RichTextNode[]
}

const props = withDefaults(defineProps<{
  value: unknown
  emptyText?: string
}>(), {
  emptyText: '',
})

const isEmpty = computed(() => richTextToPlainText(props.value).trim() === '')

function renderRichText(value: unknown): VNodeChild[] {
  if (!isRichTextNode(value)) {
    return typeof value === 'string' && value.trim() !== '' ? [h('p', value)] : []
  }

  return renderRichTextNodes(value.content ?? [])
}

function renderRichTextNodes(nodes: RichTextNode[]): VNodeChild[] {
  return nodes.map((node, index) => renderRichTextNode(node, index))
}

function renderRichTextNode(node: RichTextNode, index: number): VNodeChild {
  const children = renderRichTextNodes(node.content ?? [])

  if (node.type === 'paragraph') {
    return h('p', { key: index }, children.length ? children : '')
  }

  if (node.type === 'heading') {
    const level = typeof node.attrs?.level === 'number' ? node.attrs.level : 2
    const tag = `h${Math.min(Math.max(level, 2), 4)}`

    return h(tag, { key: index }, children)
  }

  if (node.type === 'bulletList') {
    return h('ul', { key: index }, children)
  }

  if (node.type === 'orderedList') {
    return h('ol', { key: index }, children)
  }

  if (node.type === 'listItem') {
    return h('li', { key: index }, children)
  }

  if (node.type === 'blockquote') {
    return h('blockquote', { key: index }, children)
  }

  if (node.type === 'codeBlock') {
    return h('pre', { key: index }, [h('code', richTextToPlainText(node))])
  }

  if (node.type === 'hardBreak') {
    return h('br', { key: index })
  }

  if (node.type === 'horizontalRule') {
    return h('hr', { key: index })
  }

  if (node.type === 'text') {
    return renderMarkedText(node, index)
  }

  return h('span', { key: index }, children)
}

function renderMarkedText(node: RichTextNode, index: number): VNodeChild {
  let renderedText: VNodeChild = node.text ?? ''

  for (const mark of node.marks ?? []) {
    if (mark.type === 'bold') {
      renderedText = h('strong', [renderedText])
    } else if (mark.type === 'italic') {
      renderedText = h('em', [renderedText])
    } else if (mark.type === 'code') {
      renderedText = h('code', [renderedText])
    } else if (mark.type === 'link' && typeof mark.attrs?.href === 'string') {
      renderedText = h('a', { href: mark.attrs.href, target: '_blank', rel: 'noreferrer' }, [renderedText])
    }
  }

  return h('span', { key: index }, [renderedText])
}

function richTextToPlainText(value: unknown): string {
  if (Array.isArray(value)) {
    return value.map(richTextToPlainText).filter(Boolean).join('\n')
  }

  if (!value || typeof value !== 'object') {
    return typeof value === 'string' ? value : ''
  }

  if ('text' in value && typeof value.text === 'string') {
    return value.text
  }

  return 'content' in value ? richTextToPlainText(value.content) : ''
}

function isRichTextNode(value: unknown): value is RichTextNode {
  return Boolean(value && typeof value === 'object' && 'type' in value)
}
</script>

<template>
  <div class="rich-text-renderer">
    <p v-if="isEmpty && emptyText">
      {{ emptyText }}
    </p>
    <component
      v-else
      :is="() => renderRichText(value)"
    />
  </div>
</template>

<style scoped>
.rich-text-renderer {
  display: grid;
  gap: 10px;
  color: var(--color-text-muted);
  line-height: 1.6;
}

.rich-text-renderer :deep(p),
.rich-text-renderer :deep(ul),
.rich-text-renderer :deep(ol),
.rich-text-renderer :deep(blockquote),
.rich-text-renderer :deep(pre) {
  margin: 0;
}

.rich-text-renderer :deep(h2),
.rich-text-renderer :deep(h3),
.rich-text-renderer :deep(h4) {
  margin: 4px 0 0;
  color: var(--color-text);
  font-weight: 800;
  line-height: 1.25;
}

.rich-text-renderer :deep(h2) {
  font-size: 20px;
}

.rich-text-renderer :deep(h3) {
  font-size: 17px;
}

.rich-text-renderer :deep(h4) {
  font-size: 15px;
}

.rich-text-renderer :deep(ul),
.rich-text-renderer :deep(ol) {
  display: grid;
  gap: 5px;
  padding-left: 22px;
}

.rich-text-renderer :deep(blockquote) {
  padding-left: 12px;
  border-left: 3px solid var(--color-border);
}

.rich-text-renderer :deep(a) {
  color: var(--color-primary);
}

.rich-text-renderer :deep(code) {
  padding: 2px 5px;
  background: var(--color-bg-soft);
  border-radius: var(--radius-sm);
  font-size: 0.92em;
}

.rich-text-renderer :deep(pre) {
  overflow-x: auto;
  padding: 10px;
  background: var(--color-bg-soft);
  border-radius: var(--radius-md);
}

.rich-text-renderer :deep(pre code) {
  padding: 0;
  background: transparent;
}
</style>
