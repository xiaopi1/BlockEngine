<template>
	<div
		class="markdown-body be-project-description"
		v-html="renderHighlightedString(description ?? '')"
	/>
</template>
<script setup lang="ts">
import { renderHighlightedString } from '@modrinth/utils'

withDefaults(
	defineProps<{
		description: string
	}>(),
	{},
)
</script>

<style scoped>
.be-project-description {
	border-top: 4px solid color-mix(in srgb, var(--be-moss) 58%, transparent);
}
</style>
