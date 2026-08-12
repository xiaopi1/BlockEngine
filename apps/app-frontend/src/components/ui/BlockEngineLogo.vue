<script setup lang="ts">
import logoUrl from '@/assets/block-engine-logo.png'
</script>

<template>
	<div aria-label="方块引擎" class="block-engine-brand">
		<img class="block-engine-mark" :src="logoUrl" alt="" aria-hidden="true" />
		<span class="block-engine-wordmark">
			<strong>方块引擎</strong>
			<small>BLOCK ENGINE</small>
		</span>
	</div>
</template>

<style scoped>
.block-engine-brand {
	display: inline-flex;
	height: 100%;
	align-items: center;
	gap: 0.62rem;
	color: var(--color-contrast);
	user-select: none;
}

.block-engine-mark {
	width: 2.35rem;
	height: 2.35rem;
	object-fit: contain;
	filter: drop-shadow(0 0.28rem 0.38rem rgb(25 20 15 / 24%));
}

.block-engine-wordmark {
	display: flex;
	flex-direction: column;
	line-height: 1;
}

.block-engine-wordmark strong {
	font-size: 0.95rem;
	font-weight: 800;
	letter-spacing: 0.04em;
}

.block-engine-wordmark small {
	margin-top: 0.28rem;
	color: var(--color-secondary);
	font-size: 0.55rem;
	font-weight: 700;
	letter-spacing: 0.12em;
}
</style>
