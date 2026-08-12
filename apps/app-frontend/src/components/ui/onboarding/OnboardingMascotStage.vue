<script setup lang="ts">
import logoUrl from '@/assets/block-engine-logo.png'

defineProps<{
	alt: string
}>()
</script>

<template>
	<div class="onboarding-mascot" role="img" :aria-label="alt">
		<span class="onboarding-guide-orbit onboarding-guide-orbit-a" aria-hidden="true"></span>
		<span class="onboarding-guide-orbit onboarding-guide-orbit-b" aria-hidden="true"></span>
		<img class="onboarding-guide-logo" :src="logoUrl" alt="" aria-hidden="true" />
	</div>
</template>

<style scoped lang="scss">
.onboarding-mascot {
	position: relative;
	display: grid;
	width: clamp(6rem, 11vw, 9rem);
	aspect-ratio: 1;
	place-items: center;
	isolation: isolate;
}

.onboarding-guide-logo {
	z-index: 2;
	width: 74%;
	height: 74%;
	object-fit: contain;
	filter: drop-shadow(0 0.8rem 1.1rem color-mix(in srgb, var(--color-brand) 26%, transparent));
}

.onboarding-guide-orbit {
	position: absolute;
	inset: 12%;
	z-index: 1;
	border: 1px solid color-mix(in srgb, var(--color-brand) 42%, transparent);
	border-radius: 28% 44% 32% 46%;
	animation: block-engine-guide-orbit 8s linear infinite;
}

.onboarding-guide-orbit-b {
	inset: 21%;
	border-style: dashed;
	animation-direction: reverse;
	animation-duration: 5s;
}

@keyframes block-engine-guide-orbit {
	to {
		transform: rotate(360deg);
	}
}

@media (prefers-reduced-motion: reduce) {
	.onboarding-guide-orbit {
		animation: none;
	}
}
</style>
