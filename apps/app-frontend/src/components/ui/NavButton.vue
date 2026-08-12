<template>
	<RouterLink
		v-if="typeof to === 'string' && !disabled"
		:to="to"
		v-bind="$attrs"
		:active-class="isSubpage ? '' : undefined"
		:class="{
			'router-link-active': isPrimary && isPrimary(route),
			'subpage-active': isSubpage && isSubpage(route),
		}"
		class="block-nav-button"
	>
		<span class="block-nav-icon"><slot /></span>
		<span v-if="label" class="block-nav-label">{{ label }}</span>
	</RouterLink>
	<button
		v-else-if="typeof to === 'string'"
		v-bind="$attrs"
		type="button"
		aria-disabled="true"
		tabindex="-1"
		:class="{
			'router-link-active': isPrimary && isPrimary(route),
			'subpage-active': isSubpage && isSubpage(route),
		}"
		class="block-nav-button"
		@click.prevent
		@keydown.enter.prevent
		@keyup.enter.prevent
		@keydown.space.prevent
		@keyup.space.prevent
	>
		<span class="block-nav-icon"><slot /></span>
		<span v-if="label" class="block-nav-label">{{ label }}</span>
	</button>
	<button
		v-else
		v-bind="$attrs"
		class="block-nav-button button-animation"
		:disabled="disabled"
		@click="to"
	>
		<span class="block-nav-icon"><slot /></span>
		<span v-if="label" class="block-nav-label">{{ label }}</span>
	</button>
</template>

<script setup lang="ts">
import type { RouteLocationNormalizedLoaded } from 'vue-router'
import { RouterLink, useRoute } from 'vue-router'

const route = useRoute()

type RouteFunction = (route: RouteLocationNormalizedLoaded) => boolean

withDefaults(
	defineProps<{
		to: (() => void) | string
		isPrimary?: RouteFunction
		isSubpage?: RouteFunction
		highlightOverride?: boolean
		disabled?: boolean
		label?: string
	}>(),
	{
		disabled: false,
		isPrimary: undefined,
		isSubpage: undefined,
	},
)

defineOptions({
	inheritAttrs: false,
})
</script>

<style lang="scss" scoped>
.router-link-active,
.subpage-active {
	svg {
		filter: drop-shadow(0 0 0.5rem black);
	}
}

.block-nav-button {
	display: flex;
	width: 100%;
	min-height: 2.8rem;
	align-items: center;
	gap: 0.8rem;
	padding: 0.55rem 0.75rem;
	border: 0;
	border-radius: 0.65rem;
	background: transparent;
	color: var(--color-secondary);
	font: inherit;
	font-size: 0.86rem;
	font-weight: 600;
	text-align: left;
	cursor: pointer;
	transition:
		background-color 150ms ease,
		color 150ms ease,
		transform 150ms ease;
}

.block-nav-button:hover {
	background: color-mix(in srgb, #3f9972 10%, var(--color-button-bg));
	color: #3f9972;
}

.block-nav-button:active {
	transform: scale(0.985);
}

.block-nav-icon {
	display: flex;
	width: 1.35rem;
	height: 1.35rem;
	flex: 0 0 1.35rem;
	align-items: center;
	justify-content: center;
	font-size: 1.15rem;
}

.block-nav-label {
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.router-link-active {
	color: #3f9972;
	background: color-mix(in srgb, #3f9972 15%, var(--color-button-bg));
	box-shadow: inset 3px 0 #3f9972;
}

.subpage-active {
	color: #3f9972;
	background: color-mix(in srgb, #3f9972 9%, var(--color-button-bg));
}
</style>
