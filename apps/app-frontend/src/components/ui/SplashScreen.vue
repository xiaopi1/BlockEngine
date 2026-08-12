<template>
	<Transition name="splash-fade" @after-leave="onAfterLeave">
		<div v-if="!doneLoading" class="splash-screen dark">
			<div class="app-logo-wrapper" data-tauri-drag-region>
				<BlockEngineLogo class="app-logo" />
				<p class="splash-caption">正在准备你的 Minecraft 工作台</p>
				<ProgressBar class="loading-bar" :progress="Math.min(loadingProgress, 100)" />
				<span v-if="message">{{ message }}</span>
			</div>
			<div class="gradient-bg" data-tauri-drag-region></div>
			<div class="cube-bg"></div>
			<div class="base-bg"></div>
		</div>
	</Transition>
</template>

<script setup>
import { defineMessages, injectLoadingState, useVIntl } from '@modrinth/ui'
import { ref, watch } from 'vue'

import BlockEngineLogo from '@/components/ui/BlockEngineLogo.vue'
import ProgressBar from '@/components/ui/ProgressBar.vue'
import { loading_listener } from '@/helpers/events.js'

const doneLoading = ref(false)
const loadingProgress = ref(0)
const message = ref()

const MIN_DISPLAY_MS = 500
const mountedAt = Date.now()

const loading = injectLoadingState()
const { formatMessage } = useVIntl()

const messages = defineMessages({
	updatingAppDirectory: {
		id: 'app.splash.updating-app-directory',
		defaultMessage: 'Updating app directory...',
	},
	checkingForUpdates: {
		id: 'app.splash.checking-for-updates',
		defaultMessage: 'Checking for updates...',
	},
})

function onAfterLeave() {
	loading.setEnabled(true)
}

watch(
	[loading.barEnabled, loading.pending],
	([barEnabled, pending]) => {
		if (barEnabled) {
			return
		}

		if (pending) {
			loadingProgress.value = 0
			fakeLoadingIncrease()
			return
		}

		const elapsed = Date.now() - mountedAt
		const delay = Math.max(0, MIN_DISPLAY_MS - elapsed)

		setTimeout(() => {
			if (loading.pending.value) {
				return
			}
			doneLoading.value = true
		}, delay)
	},
	{ immediate: true },
)

function fakeLoadingIncrease() {
	if (loadingProgress.value < 95) {
		setTimeout(() => {
			loadingProgress.value += 2
			fakeLoadingIncrease()
		}, 5)
	}
}

loading_listener(async (e) => {
	if (e.event.type === 'directory_move') {
		loadingProgress.value = 100 * (e.fraction ?? 1)
		message.value = formatMessage(messages.updatingAppDirectory)
	} else if (e.event.type === 'checking_for_updates') {
		loadingProgress.value = 100 * (e.fraction ?? 1)
		message.value = formatMessage(messages.checkingForUpdates)
	}
})
</script>

<style scoped lang="scss">
.splash-screen {
	position: fixed;
	inset: 0;
	z-index: 10000;
}

.splash-fade-leave-active {
	transition: opacity 0.3s ease-in-out;
}

.splash-fade-leave-to {
	opacity: 0;
}

.app-logo-wrapper {
	position: absolute;
	height: 100vh;
	width: 100%;

	display: flex;
	flex-direction: column;
	justify-content: center;
	align-items: center;

	gap: 1rem;

	z-index: 9998;
}

.app-logo {
	height: 4.5rem;
	filter: drop-shadow(0 0.75rem 1.5rem rgb(25 118 210 / 16%));
}

.splash-caption {
	margin: 0;
	color: #74837a;
	font-size: 0.82rem;
	font-weight: 600;
	letter-spacing: 0.06em;
}

.loading-bar {
	max-width: 20rem;
}

.gradient-bg {
	position: absolute;
	height: 100vh;
	width: 100vw;
	background:
		radial-gradient(circle at 68% 24%, rgb(202 232 218 / 70%), transparent 34%),
		linear-gradient(145deg, rgb(251 253 252 / 92%), rgb(235 243 239 / 94%));
	z-index: 9997;
}

.cube-bg {
	position: absolute;

	left: 50%;
	top: 50%;
	transform: translate(-50%, -50%);

	width: 180vw;
	height: 180vh;
	opacity: 0.07;
	background: #f4f7f5 url('@/assets/loading/cube.png') center no-repeat;
	background-size: contain;

	z-index: 9996;
}

.base-bg {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	background: #f4f7f5;
	z-index: 9995;
}
</style>
