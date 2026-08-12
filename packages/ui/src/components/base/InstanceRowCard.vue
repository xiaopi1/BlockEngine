<script setup lang="ts">
defineProps<{
	name: string
	version?: string | null
	loader?: string | null
}>()

defineEmits<{
	select: []
}>()
</script>

<template>
	<button
		class="be-instance-row flex w-full items-center gap-3 border border-surface-4 bg-surface-2 p-3 text-left transition-all duration-200 hover:cursor-pointer hover:border-brand hover:bg-brand-highlight active:scale-[0.98]"
		@click="$emit('select')"
	>
		<slot name="prepend" />
		<div class="flex min-w-0 flex-1 flex-col">
			<span class="truncate text-sm font-semibold text-contrast">{{ name }}</span>
			<span v-if="version || loader" class="truncate text-xs text-secondary">
				{{ version ?? '—' }}<template v-if="loader"> · {{ loader }}</template>
			</span>
		</div>
		<slot name="append" />
	</button>
</template>

<style scoped>
.be-instance-row {
	min-height: 4.5rem;
	border-color: var(--be-seam);
	border-left: 3px solid color-mix(in srgb, var(--be-moss) 55%, transparent);
	border-radius: 0.45rem;
	background: var(--be-panel);
	box-shadow: none;
}

.be-instance-row:hover {
	border-left-color: var(--be-moss);
	background: color-mix(in srgb, var(--be-moss) 9%, var(--be-panel));
}
</style>
