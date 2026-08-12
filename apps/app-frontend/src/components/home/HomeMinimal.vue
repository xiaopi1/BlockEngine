<script setup lang="ts">
import {
	DownloadIcon,
	GameIcon,
	ListIcon,
	PlayIcon,
	PlusIcon,
	SpinnerIcon,
	StopCircleIcon,
	TimerIcon,
} from '@modrinth/assets'
import {
	ButtonStyled,
	commonMessages,
	defineMessages,
	injectNotificationManager,
	useRelativeTime,
	useVIntl,
} from '@modrinth/ui'
import dayjs from 'dayjs'
import { computed, onUnmounted, ref, watch } from 'vue'

import HomeGreeting from '@/components/home/HomeGreeting.vue'
import InstanceIcon from '@/components/ui/InstanceIcon.vue'
import { useMinecraftLaunchError } from '@/composables/useMinecraftLaunchError'
import { useNetworkStatus } from '@/composables/useNetworkStatus'
import { trackEvent } from '@/helpers/analytics'
import { process_listener } from '@/helpers/events'
import { install_existing_instance, install_pack_to_existing_instance } from '@/helpers/install'
import { kill, run } from '@/helpers/instance'
import { get_by_instance_id } from '@/helpers/process'
import type { GameInstance } from '@/helpers/types'
import { handleSevereError } from '@/store/error'

const props = defineProps<{
	instances: GameInstance[]
	selectedInstanceId?: string | null
	playerName?: string | null
}>()

const emit = defineEmits<{
	choose: []
	create: []
}>()

const { formatMessage } = useVIntl()
const formatRelativeTime = useRelativeTime()
const { handleError } = injectNotificationManager()
const handleMinecraftLaunchError = useMinecraftLaunchError()
const { offline } = useNetworkStatus()

const messages = defineMessages({
	chooseInstance: {
		id: 'app.home.minimal.choose-instance',
		defaultMessage: 'Choose instance',
	},
	changeInstance: {
		id: 'app.home.minimal.change-instance',
		defaultMessage: 'Change Home instance',
	},
	createInstance: {
		id: 'app.home.instances.create',
		defaultMessage: 'Create instance',
	},
	noInstances: {
		id: 'app.home.instances.empty',
		defaultMessage: 'No instances yet',
	},
	loading: {
		id: 'app.instance.loading',
		defaultMessage: 'Instance is loading...',
	},
	played: {
		id: 'app.instance.played',
		defaultMessage: 'Played {time}',
	},
	neverPlayed: {
		id: 'app.instance.never-played',
		defaultMessage: 'Never played',
	},
	offlineInstalledOnly: {
		id: 'app.instance.offline-installed-only',
		defaultMessage: 'Offline mode can only launch fully downloaded instances.',
	},
})

const selectedInstance = computed(() =>
	props.instances.find((instance) => instance.id === props.selectedInstanceId),
)
const running = ref(false)
const loading = ref(false)
const currentEvent = ref<string | null>(null)
const installed = computed(() => selectedInstance.value?.install_stage === 'installed')
const installing = computed(
	() => selectedInstance.value?.install_stage.includes('installing') ?? false,
)
const busy = computed(
	() => loading.value || installing.value || (currentEvent.value === 'launched' && !running.value),
)

const lastPlayed = computed(() => {
	if (!selectedInstance.value?.last_played) return formatMessage(messages.neverPlayed)
	return formatMessage(messages.played, {
		time: formatRelativeTime(dayjs(selectedInstance.value.last_played).toISOString()),
	})
})

async function refreshProcessState() {
	if (!selectedInstance.value) {
		running.value = false
		return
	}

	const processes = await get_by_instance_id(selectedInstance.value.id).catch((error) => {
		handleError(error)
		return []
	})
	running.value = processes.length > 0
}

async function playInstance() {
	const instance = selectedInstance.value
	if (!instance) return

	loading.value = true
	try {
		await run(instance.id)
		trackEvent('InstanceStart', {
			loader: instance.loader,
			game_version: instance.game_version,
			source: 'HomeMinimal',
		})
	} catch (error) {
		const handled = await handleMinecraftLaunchError(error, {
			instance_id: instance.id,
			instance_name: instance.name,
		})
		if (!handled) handleSevereError(error, { instanceId: instance.id })
	} finally {
		loading.value = false
		await refreshProcessState()
	}
}

async function stopInstance() {
	const instance = selectedInstance.value
	if (!instance) return

	await kill(instance.id).catch(handleError)
	running.value = false
	trackEvent('InstanceStop', {
		loader: instance.loader,
		game_version: instance.game_version,
		source: 'HomeMinimal',
	})
}

async function installInstance() {
	const instance = selectedInstance.value
	if (!instance) return

	loading.value = true
	try {
		if (
			instance.install_stage !== 'pack_installed' &&
			(instance.link?.type === 'modrinth_modpack' ||
				instance.link?.type === 'server_project_modpack')
		) {
			await install_pack_to_existing_instance(instance.id, {
				type: 'fromVersionId',
				project_id: instance.link.project_id ?? instance.link.server_project_id ?? '',
				version_id: instance.link.version_id ?? instance.link.content_version_id ?? '',
				title: instance.name,
			})
		} else {
			await install_existing_instance(instance.id, false)
		}
	} catch (error) {
		handleError(error)
	} finally {
		loading.value = false
	}
}

watch(
	() => props.selectedInstanceId,
	() => {
		currentEvent.value = null
		void refreshProcessState()
	},
)

await refreshProcessState()

const unlistenProcess = await process_listener((event: { instance_id: string; event: string }) => {
	if (event.instance_id !== selectedInstance.value?.id) return
	currentEvent.value = event.event
	if (event.event === 'finished') running.value = false
	else void refreshProcessState()
})

onUnmounted(() => {
	unlistenProcess()
})
</script>

<template>
	<section data-onboarding-id="home-instances" class="minimal-home-stage">
		<div class="world-workbench">
			<header class="world-workbench-header">
				<div>
					<p class="world-eyebrow"><span></span> WORLD WORKBENCH</p>
					<HomeGreeting :player-name="playerName" variant="minimal" />
				</div>
				<div class="world-weather" aria-label="Launcher status">
					<span class="world-weather-sun"></span>
					<div><strong>世界就绪</strong><small>本地环境 · 在线服务</small></div>
				</div>
			</header>

			<div class="world-slice" :class="{ 'is-empty': !selectedInstance }">
				<div class="world-sky-pixels" aria-hidden="true"><i></i><i></i><i></i></div>
				<div class="world-glass-sheen" aria-hidden="true"></div>
				<div class="world-ground" aria-hidden="true"><i></i><i></i><i></i><i></i></div>

				<template v-if="selectedInstance">
					<div class="world-primary">
						<div class="world-status-chip"><span></span> 当前世界</div>
						<router-link
							:to="`/instance/${encodeURIComponent(selectedInstance.id)}`"
							class="world-instance-link"
						>
							<InstanceIcon
								class="world-instance-icon"
								:icon-path="selectedInstance.icon_path"
								:instance-id="selectedInstance.id"
							/>
							<div class="world-instance-copy">
								<h2>{{ selectedInstance.name }}</h2>
								<p>
									<GameIcon aria-hidden="true" /> {{ selectedInstance.loader }} ·
									{{ selectedInstance.game_version }}
								</p>
							</div>
						</router-link>

						<div class="world-launch-row">
							<ButtonStyled v-if="running" color="red" size="large">
								<button class="world-launch-button" @click="stopInstance">
									<StopCircleIcon aria-hidden="true" />
									<span class="truncate">{{ formatMessage(commonMessages.stopButton) }}</span>
								</button>
							</ButtonStyled>
							<ButtonStyled v-else-if="busy" size="large">
								<button class="world-launch-button" disabled>
									<SpinnerIcon class="animate-spin" aria-hidden="true" />
									<span class="truncate">
										{{
											formatMessage(installing ? commonMessages.installingLabel : messages.loading)
										}}
									</span>
								</button>
							</ButtonStyled>
							<ButtonStyled v-else-if="installed" color="brand" size="large">
								<button class="world-launch-button" @click="playInstance">
									<PlayIcon class="translate-x-px" aria-hidden="true" />
									<span class="truncate">进入世界</span>
								</button>
							</ButtonStyled>
							<ButtonStyled v-else color="brand" size="large">
								<button
									v-tooltip="offline ? formatMessage(messages.offlineInstalledOnly) : undefined"
									class="world-launch-button"
									:disabled="offline"
									@click="installInstance"
								>
									<DownloadIcon aria-hidden="true" />
									<span class="truncate">{{ formatMessage(commonMessages.installButton) }}</span>
								</button>
							</ButtonStyled>

							<ButtonStyled circular size="large" type="transparent">
								<button
									v-tooltip="formatMessage(messages.changeInstance)"
									:aria-label="formatMessage(messages.changeInstance)"
									@click="emit('choose')"
								>
									<ListIcon aria-hidden="true" />
								</button>
							</ButtonStyled>
						</div>
					</div>

					<aside class="world-inventory">
						<p class="world-panel-label">世界信息</p>
						<div class="world-stat">
							<GameIcon /><span>运行核心</span><strong>{{ selectedInstance.loader }}</strong>
						</div>
						<div class="world-stat">
							<TimerIcon /><span>上次游玩</span><strong>{{ lastPlayed }}</strong>
						</div>
						<button class="world-change-button" type="button" @click="emit('choose')">
							<ListIcon /> 切换世界
						</button>
					</aside>
				</template>

				<div v-else class="world-empty-state">
					<div class="world-empty-cube"><ListIcon aria-hidden="true" /></div>
					<p class="world-panel-label">新的存档位</p>
					<h2>
						{{
							formatMessage(instances.length > 0 ? messages.chooseInstance : messages.noInstances)
						}}
					</h2>
					<p>选择已安装内容，或从资源中心创建一个新的 Minecraft 世界。</p>
					<div class="world-empty-actions">
						<ButtonStyled color="brand" size="large">
							<button v-if="instances.length > 0" @click="emit('choose')">
								<ListIcon aria-hidden="true" />
								{{ formatMessage(messages.chooseInstance) }}
							</button>
							<button v-else @click="emit('create')">
								<PlusIcon aria-hidden="true" />
								{{ formatMessage(messages.createInstance) }}
							</button>
						</ButtonStyled>
					</div>
				</div>
			</div>
		</div>
	</section>
</template>

<style scoped>
.minimal-home-stage {
	min-height: calc(100vh - var(--top-bar-height));
	padding: clamp(1.25rem, 3vw, 2.6rem) clamp(1rem, 3.5vw, 3.5rem) 7.5rem;
	box-sizing: border-box;
}

.world-workbench {
	width: min(1120px, 100%);
	margin: 0 auto;
}

.world-workbench-header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 2rem;
	margin-bottom: 1.35rem;
}

.world-eyebrow,
.world-panel-label {
	margin: 0 0 0.45rem;
	color: #28785c;
	font-size: 0.68rem;
	font-weight: 850;
	letter-spacing: 0.14em;
	text-transform: uppercase;
}

.world-eyebrow span,
.world-status-chip span {
	display: inline-block;
	width: 0.52rem;
	height: 0.52rem;
	margin-right: 0.42rem;
	background: #69c46f;
	box-shadow: 0 0 0 3px rgb(105 196 111 / 18%);
}

.world-weather {
	display: flex;
	min-width: 12.5rem;
	align-items: center;
	gap: 0.75rem;
	padding: 0.72rem 0.9rem;
	border: 1px solid var(--be-chrome-border);
	border-radius: 1rem;
	background: var(--be-glass);
	box-shadow: 0 0.7rem 2rem var(--be-window-shadow);
	backdrop-filter: blur(18px) saturate(130%);
}

.world-weather strong,
.world-weather small {
	display: block;
}
.world-weather strong {
	color: var(--color-contrast);
	font-size: 0.78rem;
}
.world-weather small {
	margin-top: 0.12rem;
	color: var(--color-secondary);
	font-size: 0.66rem;
}
.world-weather-sun {
	width: 1.55rem;
	height: 1.55rem;
	background: #f3bf45;
	box-shadow: 0 0 1.2rem rgb(243 191 69 / 50%);
}

.world-slice {
	position: relative;
	display: grid;
	grid-template-columns: minmax(0, 1.55fr) minmax(245px, 0.65fr);
	min-height: 430px;
	overflow: hidden;
	border: 1px solid rgb(255 255 255 / 84%);
	border-radius: 1.65rem;
	background: linear-gradient(
		180deg,
		rgb(211 243 255 / 92%) 0 54%,
		rgb(135 210 236 / 72%) 54% 64%,
		rgb(98 172 101 / 92%) 64% 68%,
		rgb(108 79 56 / 92%) 68% 100%
	);
	box-shadow:
		0 1.8rem 5rem rgb(33 73 84 / 17%),
		inset 0 1px rgb(255 255 255 / 96%);
	isolation: isolate;
}

.world-slice::after {
	content: '';
	position: absolute;
	inset: 0;
	z-index: -1;
	background:
		repeating-linear-gradient(0deg, transparent 0 31px, rgb(255 255 255 / 8%) 31px 32px),
		repeating-linear-gradient(90deg, transparent 0 31px, rgb(255 255 255 / 8%) 31px 32px);
	pointer-events: none;
}

.world-glass-sheen {
	position: absolute;
	inset: 0;
	z-index: 0;
	background: linear-gradient(
		118deg,
		rgb(255 255 255 / 38%),
		transparent 38% 70%,
		rgb(255 255 255 / 16%)
	);
	pointer-events: none;
}
.world-sky-pixels {
	position: absolute;
	inset: 0;
	pointer-events: none;
}
.world-sky-pixels i {
	position: absolute;
	display: block;
	background: rgb(255 255 255 / 68%);
	box-shadow:
		1.8rem 0 rgb(255 255 255 / 68%),
		0.9rem -0.7rem rgb(255 255 255 / 68%);
}
.world-sky-pixels i:nth-child(1) {
	top: 14%;
	left: 13%;
	width: 2.2rem;
	height: 0.7rem;
}
.world-sky-pixels i:nth-child(2) {
	top: 24%;
	left: 57%;
	width: 2.7rem;
	height: 0.7rem;
	opacity: 0.68;
}
.world-sky-pixels i:nth-child(3) {
	top: 10%;
	right: 8%;
	width: 1.6rem;
	height: 0.55rem;
	opacity: 0.5;
}
.world-ground {
	position: absolute;
	inset: 68% 0 0;
	z-index: 0;
	background: linear-gradient(180deg, transparent, rgb(46 36 29 / 20%));
	pointer-events: none;
}
.world-ground i {
	position: absolute;
	width: 1rem;
	height: 1rem;
	background: rgb(55 43 33 / 18%);
}
.world-ground i:nth-child(1) {
	left: 8%;
	top: 25%;
}
.world-ground i:nth-child(2) {
	left: 34%;
	top: 56%;
}
.world-ground i:nth-child(3) {
	left: 63%;
	top: 33%;
}
.world-ground i:nth-child(4) {
	right: 8%;
	top: 68%;
}

.world-primary,
.world-empty-state {
	position: relative;
	z-index: 2;
	display: flex;
	min-width: 0;
	flex-direction: column;
	justify-content: center;
	padding: clamp(2rem, 5vw, 4.2rem);
}

.world-status-chip {
	width: max-content;
	margin-bottom: 1.15rem;
	padding: 0.42rem 0.62rem;
	border: 1px solid rgb(255 255 255 / 74%);
	border-radius: 0.55rem;
	background: rgb(244 253 255 / 65%);
	color: #34545e;
	font-size: 0.68rem;
	font-weight: 750;
	backdrop-filter: blur(14px);
}

.world-instance-link {
	display: flex;
	min-width: 0;
	align-items: center;
	gap: 1.25rem;
	color: inherit;
	text-decoration: none;
}
.world-instance-icon {
	width: 5.2rem;
	height: 5.2rem;
	flex: 0 0 5.2rem;
	filter: drop-shadow(0 0.8rem 1rem rgb(28 64 74 / 20%));
	image-rendering: pixelated;
	transition: transform 180ms ease;
}
.world-instance-link:hover .world-instance-icon {
	transform: translateY(-0.2rem) rotate(-2deg);
}
.world-instance-copy {
	min-width: 0;
}
.world-instance-copy h2 {
	margin: 0;
	overflow: hidden;
	color: #152f38;
	font-size: clamp(1.9rem, 4vw, 3.25rem);
	line-height: 1;
	text-overflow: ellipsis;
	white-space: nowrap;
	letter-spacing: -0.045em;
}
.world-instance-copy p {
	display: flex;
	align-items: center;
	gap: 0.45rem;
	margin: 0.75rem 0 0;
	color: #41616a;
	font-size: 0.82rem;
	font-weight: 650;
	text-transform: capitalize;
}
.world-instance-copy p :deep(svg) {
	width: 1rem;
	height: 1rem;
}
.world-launch-row {
	display: flex;
	align-items: center;
	gap: 0.55rem;
	margin-top: 2rem;
}
.world-launch-button {
	min-width: 10.5rem;
	justify-content: center;
	font-weight: 800;
}

.world-inventory {
	position: relative;
	z-index: 2;
	align-self: center;
	margin: 1.2rem 1.2rem 1.2rem 0;
	padding: 1.2rem;
	border: 1px solid rgb(255 255 255 / 72%);
	border-radius: 1.15rem;
	background: linear-gradient(145deg, rgb(251 255 255 / 72%), rgb(220 240 244 / 56%));
	box-shadow:
		0 1.2rem 3rem rgb(31 70 80 / 16%),
		inset 0 1px rgb(255 255 255 / 88%);
	backdrop-filter: blur(22px) saturate(135%);
}

.world-stat {
	display: grid;
	grid-template-columns: 1.5rem 1fr;
	gap: 0 0.55rem;
	padding: 0.85rem 0;
	border-bottom: 1px solid rgb(45 89 101 / 10%);
	color: #597078;
}
.world-stat :deep(svg) {
	grid-row: 1 / 3;
	width: 1.15rem;
	height: 1.15rem;
	margin-top: 0.1rem;
	color: #2b8fe6;
}
.world-stat span {
	font-size: 0.66rem;
}
.world-stat strong {
	overflow: hidden;
	color: #263d45;
	font-size: 0.78rem;
	text-overflow: ellipsis;
	white-space: nowrap;
	text-transform: capitalize;
}
.world-change-button {
	display: flex;
	width: 100%;
	align-items: center;
	justify-content: center;
	gap: 0.45rem;
	margin-top: 1rem;
	padding: 0.72rem;
	border: 1px solid rgb(43 143 230 / 22%);
	border-radius: 0.68rem;
	background: rgb(255 255 255 / 52%);
	color: #176eb4;
	font: inherit;
	font-size: 0.76rem;
	font-weight: 750;
	cursor: pointer;
}
.world-change-button:hover {
	background: rgb(255 255 255 / 76%);
}
.world-change-button :deep(svg) {
	width: 1rem;
	height: 1rem;
}

.world-slice.is-empty {
	display: block;
}
.world-empty-state {
	width: min(34rem, 100%);
	min-height: 430px;
	box-sizing: border-box;
}
.world-empty-cube {
	display: flex;
	width: 4rem;
	height: 4rem;
	align-items: center;
	justify-content: center;
	margin-bottom: 1.2rem;
	background: rgb(244 253 255 / 64%);
	color: #2b8fe6;
	box-shadow: 0.7rem 0.7rem 0 rgb(29 98 92 / 13%);
	backdrop-filter: blur(14px);
}
.world-empty-cube :deep(svg) {
	width: 1.8rem;
	height: 1.8rem;
}
.world-empty-state h2 {
	margin: 0;
	color: #18343c;
	font-size: 2.2rem;
	letter-spacing: -0.035em;
}
.world-empty-state > p:not(.world-panel-label) {
	max-width: 30rem;
	margin: 0.75rem 0 1.5rem;
	color: #49656d;
	line-height: 1.65;
}
.world-empty-actions {
	display: flex;
}

@media (max-width: 860px) {
	.world-workbench-header {
		align-items: stretch;
		flex-direction: column;
		gap: 0.8rem;
	}
	.world-weather {
		align-self: flex-start;
	}
	.world-slice {
		grid-template-columns: 1fr;
	}
	.world-inventory {
		display: none;
	}
	.world-primary {
		min-height: 430px;
	}
}

@media (max-width: 580px) {
	.world-weather {
		display: none;
	}
	.world-instance-link {
		align-items: flex-start;
		flex-direction: column;
	}
	.world-instance-copy h2 {
		white-space: normal;
	}
}
</style>
