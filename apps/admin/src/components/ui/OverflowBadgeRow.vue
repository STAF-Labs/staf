<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = defineProps<{
  values: string[]
  overflowLabel: string
}>()

const container = ref<HTMLElement | null>(null)
const measureBadges = ref<HTMLElement[]>([])
const overflowBadge = ref<HTMLElement | null>(null)
const visibleCount = ref(props.values.length)
let resizeObserver: ResizeObserver | null = null

const hiddenCount = computed(() => Math.max(0, props.values.length - visibleCount.value))
const visibleValues = computed(() => props.values.slice(0, visibleCount.value))
const overflowText = computed(() => `+${hiddenCount.value} ${props.overflowLabel}`)

function setMeasureBadge(element: unknown, index: number): void {
  if (element instanceof HTMLElement) {
    measureBadges.value[index] = element
  }
}

async function updateVisibleCount(): Promise<void> {
  await nextTick()

  const width = container.value?.clientWidth ?? 0

  if (!width || props.values.length === 0) {
    visibleCount.value = props.values.length

    return
  }

  const badgeWidths = measureBadges.value
    .slice(0, props.values.length)
    .map((badge) => badge.offsetWidth)
  const gap = 6
  const totalWidth = badgeWidths.reduce((sum, badgeWidth, index) => {
    return sum + badgeWidth + (index > 0 ? gap : 0)
  }, 0)

  if (totalWidth <= width) {
    visibleCount.value = props.values.length

    return
  }

  for (let count = props.values.length - 1; count >= 0; count -= 1) {
    const remaining = props.values.length - count

    if (remaining === 0) {
      visibleCount.value = count

      return
    }

    const badgesWidth = badgeWidths.slice(0, count).reduce((sum, badgeWidth, index) => {
      return sum + badgeWidth + (index > 0 ? gap : 0)
    }, 0)
    const previousHiddenCount = visibleCount.value
    visibleCount.value = count
    await nextTick()
    const overflowWidth = overflowBadge.value?.offsetWidth ?? 0
    const parts = count > 0 ? count + 1 : 1
    const rowWidth = badgesWidth + overflowWidth + Math.max(0, parts - 1) * gap

    if (rowWidth <= width || count === 0) {
      return
    }

    visibleCount.value = previousHiddenCount
  }
}

watch(
  () => [props.values, props.overflowLabel],
  () => {
    measureBadges.value = []
    visibleCount.value = props.values.length
    void updateVisibleCount()
  },
  { deep: true },
)

onMounted(() => {
  resizeObserver = new ResizeObserver(() => {
    void updateVisibleCount()
  })

  if (container.value) {
    resizeObserver.observe(container.value)
  }

  void updateVisibleCount()
})

onBeforeUnmount(() => {
  resizeObserver?.disconnect()
})
</script>

<template>
  <div ref="container" class="overflow-badge-row">
    <div class="overflow-badge-row__measure" aria-hidden="true">
      <span
        v-for="(value, index) in values"
        :key="`measure:${value}:${index}`"
        :ref="(element) => setMeasureBadge(element, index)"
        class="overflow-badge-row__badge"
      >
        {{ value }}
      </span>
    </div>

    <span
      v-for="(value, index) in visibleValues"
      :key="`${value}:${index}`"
      class="overflow-badge-row__badge"
    >
      {{ value }}
    </span>
    <span
      v-if="hiddenCount > 0"
      ref="overflowBadge"
      class="overflow-badge-row__badge overflow-badge-row__badge--overflow"
    >
      {{ overflowText }}
    </span>
  </div>
</template>

<style scoped>
.overflow-badge-row {
  position: relative;
  display: flex;
  gap: 6px;
  min-width: 0;
  overflow: hidden;
  white-space: nowrap;
}

.overflow-badge-row__measure {
  position: absolute;
  inset: auto auto 100% 0;
  display: flex;
  gap: 6px;
  visibility: hidden;
  pointer-events: none;
}

.overflow-badge-row__badge {
  flex: 0 0 auto;
  max-width: 100%;
  padding: 4px 8px;
  overflow: hidden;
  color: var(--color-text-muted);
  background: var(--color-bg-soft);
  border: 1px solid var(--color-border);
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  line-height: 1.15;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.overflow-badge-row__badge--overflow {
  color: var(--color-primary);
  background: color-mix(in srgb, var(--color-primary) 9%, transparent);
  border-color: color-mix(in srgb, var(--color-primary) 22%, var(--color-border));
}
</style>
