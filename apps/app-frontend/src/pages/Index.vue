<script setup lang="ts">
import {
	CheckIcon,
	GridIcon,
	LayoutTemplateIcon,
	MinimizeIcon,
	MoveIcon,
	PencilIcon,
	PlusIcon,
	RotateCounterClockwiseIcon,
} from '@modrinth/assets'
import {
	defineMessages,
	injectNotificationManager,
	injectPageContext,
	useVIntl,
} from '@modrinth/ui'
import { computed, onUnmounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import {
	createDefaultHomeDashboard,
	createHomeDashboardSaveQueue,
	type HomeDashboardConfig,
	normalizeHomeDashboard,
	restoreCompleteHomeDashboard,
} from '@/components/home/home-dashboard'
import { getActivePlayerName } from '@/components/home/home-utils'
import HomeDailyChallenge from '@/components/home/HomeDailyChallenge.vue'
import HomeDashboard from '@/components/home/HomeDashboard.vue'
import HomeInstancePickerModal from '@/components/home/HomeInstancePickerModal.vue'
import HomeMinimal from '@/components/home/HomeMinimal.vue'
import HomeMinecraftNews from '@/components/home/HomeMinecraftNews.vue'
import HomePlayInsights from '@/components/home/HomePlayInsights.vue'
import { useNetworkStatus } from '@/composables/useNetworkStatus'
import { get_default_user, users } from '@/helpers/auth'
import { instance_listener } from '@/helpers/events'
import { list } from '@/helpers/instance'
import { get as getSettings, set as setSettings } from '@/helpers/settings'
import type { GameInstance } from '@/helpers/types'
import { useBreadcrumbs } from '@/store/breadcrumbs'
import { useTheming } from '@/store/state'
import type { HomeLayout } from '@/store/theme'

const { handleError } = injectNotificationManager()
const route = useRoute()
const router = useRouter()
const breadcrumbs = useBreadcrumbs()
const { formatMessage } = useVIntl()
const { offline } = useNetworkStatus()
const themeStore = useTheming()
const pageContext = injectPageContext()

const messages = defineMessages({
	home: { id: 'app.home.breadcrumb', defaultMessage: 'Home' },
	switchToMinimal: {
		id: 'app.home.layout.switch-to-minimal',
		defaultMessage: 'Switch to Minimal Home',
	},
	switchToInformation: {
		id: 'app.home.layout.switch-to-information',
		defaultMessage: 'Switch to Information Home',
	},
	homeLayoutToggle: {
		id: 'app.home.layout.toggle',
		defaultMessage: 'Minimal Home',
	},
	switchToGridWidgetLayout: {
		id: 'app.home.widgets.layout.switch-to-grid',
		defaultMessage: 'Switch to grid widget layout',
	},
	switchToFreeWidgetLayout: {
		id: 'app.home.widgets.layout.switch-to-free',
		defaultMessage: 'Switch to free widget layout',
	},
	widgetLayoutToggle: {
		id: 'app.home.widgets.layout.toggle',
		defaultMessage: 'Widget layout mode',
	},
	resetWidgets: {
		id: 'app.home.widgets.reset-confirm',
		defaultMessage: 'Restore the default widget layout?',
	},
	customizeWidgets: {
		id: 'app.home.widgets.customize',
		defaultMessage: 'Customize widgets',
	},
	doneEditing: { id: 'app.home.widgets.done', defaultMessage: 'Finish editing' },
	addWidget: { id: 'app.home.widgets.add', defaultMessage: 'Add widget' },
	resetWidgetLayout: {
		id: 'app.home.widgets.reset',
		defaultMessage: 'Restore default widgets',
	},
})

breadcrumbs.setRootContext({ name: formatMessage(messages.home), link: route.path })

const instances = ref<GameInstance[]>([])
const playerName = ref<string | null>(null)
const dashboardConfig = ref<HomeDashboardConfig | null>(null)
const dashboard = ref<InstanceType<typeof HomeDashboard>>()
const dashboardEditing = ref(false)
const instancePicker = ref<InstanceType<typeof HomeInstancePickerModal>>()
const isMinimal = computed(() => themeStore.homeLayout === 'minimal')
const companionDashboardConfig = computed<HomeDashboardConfig | null>(() => {
	return dashboardConfig.value
})
const homeSelectedInstanceId = computed(
	() => themeStore.minimalHomeInstanceId ?? instances.value[0]?.id ?? null,
)
const isFreeWidgetLayout = computed(() => dashboardConfig.value?.layout === 'free')
const switchingLayout = ref(false)
const GLASS_HOME_RESTORE_KEY = 'block-engine-minecraft-glass-home-restored-v4-dashboard-layout'
const dashboardSaveQueue = createHomeDashboardSaveQueue(
	async (config) => {
		const settings = await getSettings()
		settings.home_widgets = config
		await setSettings(settings)
	},
	(config) => {
		dashboardConfig.value = config
	},
	handleError,
)
const floatingControlsStyle = computed(() => ({
	bottom: themeStore.getFeatureFlag('page_path') ? '3.5rem' : '1rem',
	right: `calc(${pageContext.floatingActionBarOffsets?.right.value ?? '0px'} + 1rem)`,
}))

async function clearMissingMinimalInstance() {
	const selectedId = themeStore.minimalHomeInstanceId
	if (!selectedId || instances.value.some((instance) => instance.id === selectedId)) return

	themeStore.minimalHomeInstanceId = null
	try {
		const settings = await getSettings()
		if (settings.minimal_home_instance_id === null) return
		settings.minimal_home_instance_id = null
		await setSettings(settings)
	} catch (error) {
		handleError(error)
	}
}

async function fetchInstances() {
	try {
		instances.value = await list()
		await clearMissingMinimalInstance()
		return true
	} catch (error) {
		handleError(error)
		return false
	}
}

async function fetchPlayerName() {
	const selectedUser = await get_default_user(offline.value).catch(() => undefined)
	if (!selectedUser) return

	const accounts = await users(offline.value).catch(() => [])
	playerName.value = getActivePlayerName(selectedUser, accounts)
}

async function loadDashboardConfig() {
	try {
		const settings = await getSettings()
		const normalized = normalizeHomeDashboard(settings.home_widgets)
		if (normalized) {
			dashboardConfig.value = normalized
			return
		}

		const config = restoreCompleteHomeDashboard(createDefaultHomeDashboard(true))
		dashboardConfig.value = config
		settings.home_widgets = config
		await setSettings(settings)
	} catch (error) {
		dashboardConfig.value = restoreCompleteHomeDashboard(createDefaultHomeDashboard(true))
		handleError(error)
	}
}

function updateDashboardConfig(config: HomeDashboardConfig) {
	const previous = dashboardConfig.value ?? config
	dashboardConfig.value = config
	void dashboardSaveQueue.enqueue(config, previous)
}

function updateCompanionDashboardConfig(config: HomeDashboardConfig) {
	updateDashboardConfig(config)
}

function resetDashboardConfig() {
	if (!window.confirm(formatMessage(messages.resetWidgets))) return
	updateDashboardConfig(restoreCompleteHomeDashboard(createDefaultHomeDashboard(true)))
}

async function selectMinimalInstance(instance: GameInstance) {
	try {
		const settings = await getSettings()
		settings.minimal_home_instance_id = instance.id
		await setSettings(settings)
		themeStore.minimalHomeInstanceId = instance.id
	} catch (error) {
		handleError(error)
	}
}

function createInstance() {
	void router.push('/create')
}

async function toggleHomeLayout() {
	if (switchingLayout.value) return

	const previousLayout = themeStore.homeLayout
	const nextLayout: HomeLayout = previousLayout === 'minimal' ? 'standard' : 'minimal'
	const previousEditing = dashboardEditing.value
	switchingLayout.value = true
	themeStore.homeLayout = nextLayout
	if (nextLayout === 'minimal') dashboardEditing.value = false

	try {
		const settings = await getSettings()
		settings.home_layout = nextLayout
		await setSettings(settings)
	} catch (error) {
		themeStore.homeLayout = previousLayout
		dashboardEditing.value = previousEditing
		handleError(error)
	} finally {
		switchingLayout.value = false
	}
}

function toggleDashboardEditing() {
	dashboardEditing.value = !dashboardEditing.value
}

function toggleWidgetLayout() {
	dashboard.value?.setLayout(isFreeWidgetLayout.value ? 'grid' : 'free')
}

function openWidgetPicker() {
	dashboard.value?.openWidgetPicker()
}

async function restoreMinecraftGlassHomeOnce() {
	if (localStorage.getItem(GLASS_HOME_RESTORE_KEY)) return

	const settings = await getSettings()
	settings.home_layout = 'standard'
	settings.theme = 'light'
	settings.accent_color = 'blue'
	settings.home_widgets = restoreCompleteHomeDashboard(
		normalizeHomeDashboard(settings.home_widgets) ?? createDefaultHomeDashboard(true),
	)
	settings.home_widgets.layout = 'grid'
	await setSettings(settings)
	themeStore.homeLayout = 'standard'
	themeStore.setThemeState('light')
	themeStore.setAccentColor('blue')
	localStorage.setItem(GLASS_HOME_RESTORE_KEY, '1')
}

await restoreMinecraftGlassHomeOnce().catch(handleError)
const instancesLoaded = await fetchInstances()
if (!instancesLoaded || instances.value.length > 0) await fetchPlayerName()
await loadDashboardConfig()

const unlistenInstance = await instance_listener(async () => {
	await fetchInstances()
})

onUnmounted(() => {
	unlistenInstance()
})
</script>

<template>
	<HomeInstancePickerModal
		ref="instancePicker"
		:instances="instances"
		:selected-instance-id="homeSelectedInstanceId"
		@select="selectMinimalInstance"
	/>
	<div class="min-h-full" translate="no" data-translation-exclude="home">
		<HomeMinimal
			:instances="instances"
			:player-name="playerName"
			:selected-instance-id="homeSelectedInstanceId"
			@choose="instancePicker?.show()"
			@create="createInstance"
		/>

		<section v-if="!isMinimal && companionDashboardConfig" class="world-records-section">
			<header class="world-records-heading">
				<span>WORLD RECORDS</span>
				<h2>世界记录与动态</h2>
			</header>
			<HomeDashboard
				ref="dashboard"
				:config="companionDashboardConfig"
				:instances="instances"
				:player-name="playerName"
				:editing="dashboardEditing"
				variant="minecraft-glass"
				@change="updateCompanionDashboardConfig"
			/>
		</section>
	</div>
	<div class="home-floating-controls" :style="floatingControlsStyle">
		<template v-if="!isMinimal">
			<button
				v-if="dashboardEditing"
				v-tooltip="formatMessage(messages.addWidget)"
				type="button"
				class="home-floating-action"
				:aria-label="formatMessage(messages.addWidget)"
				@click="openWidgetPicker"
			>
				<PlusIcon />
			</button>
			<button
				v-if="dashboardEditing"
				v-tooltip="formatMessage(messages.resetWidgetLayout)"
				type="button"
				class="home-floating-action"
				:aria-label="formatMessage(messages.resetWidgetLayout)"
				@click="resetDashboardConfig"
			>
				<RotateCounterClockwiseIcon />
			</button>
			<button
				v-tooltip="
					formatMessage(dashboardEditing ? messages.doneEditing : messages.customizeWidgets)
				"
				data-onboarding-id="home-widget-customize"
				type="button"
				class="home-floating-action"
				:class="{ 'is-active': dashboardEditing }"
				:aria-label="
					formatMessage(dashboardEditing ? messages.doneEditing : messages.customizeWidgets)
				"
				:aria-pressed="dashboardEditing"
				@click="toggleDashboardEditing"
			>
				<CheckIcon v-if="dashboardEditing" />
				<PencilIcon v-else />
			</button>
			<button
				v-if="dashboardEditing"
				v-tooltip="
					formatMessage(
						isFreeWidgetLayout
							? messages.switchToGridWidgetLayout
							: messages.switchToFreeWidgetLayout,
					)
				"
				type="button"
				role="switch"
				class="home-layout-switch home-widget-layout-switch"
				:class="{ 'is-free': isFreeWidgetLayout }"
				:aria-checked="isFreeWidgetLayout"
				:aria-label="formatMessage(messages.widgetLayoutToggle)"
				@click="toggleWidgetLayout"
			>
				<span class="home-layout-switch-option home-widget-layout-grid" aria-hidden="true">
					<GridIcon />
				</span>
				<span class="home-layout-switch-thumb" aria-hidden="true" />
				<span class="home-layout-switch-option home-widget-layout-free" aria-hidden="true">
					<MoveIcon />
				</span>
			</button>
			<span class="home-floating-divider" aria-hidden="true" />
		</template>
		<button
			v-tooltip="formatMessage(isMinimal ? messages.switchToInformation : messages.switchToMinimal)"
			data-onboarding-id="home-layout-switch"
			type="button"
			role="switch"
			class="home-layout-switch"
			:class="{ 'is-minimal': isMinimal }"
			:disabled="switchingLayout"
			:aria-checked="isMinimal"
			:aria-label="formatMessage(messages.homeLayoutToggle)"
			@click="toggleHomeLayout"
		>
			<span class="home-layout-switch-option home-layout-switch-information" aria-hidden="true">
				<LayoutTemplateIcon />
			</span>
			<span class="home-layout-switch-thumb" aria-hidden="true" />
			<span class="home-layout-switch-option home-layout-switch-minimal" aria-hidden="true">
				<MinimizeIcon />
			</span>
		</button>
	</div>
	<Teleport to="#sidebar-default-teleport-target">
		<div class="flex min-w-0 flex-col" translate="no" data-translation-exclude="home-sidebar">
			<HomePlayInsights />
			<HomeDailyChallenge />
			<HomeMinecraftNews />
		</div>
	</Teleport>
</template>

<style scoped>
.world-records-section {
	width: min(1120px, 100%);
	margin: 0 auto;
	padding: 0.75rem clamp(1rem, 3.5vw, 3.5rem) 8rem;
	box-sizing: border-box;
}

.world-records-heading {
	display: flex;
	flex-direction: column;
	gap: 0.25rem;
	margin: 0 0 1rem;
}

.world-records-heading span {
	color: var(--color-brand);
	font-size: 0.7rem;
	font-weight: 800;
	letter-spacing: 0.14em;
}

.world-records-heading h2 {
	margin: 0;
	color: var(--color-contrast);
	font-size: 1.4rem;
	font-weight: 800;
}

.home-floating-controls {
	position: fixed;
	z-index: 40;
	display: flex;
	height: 2.5rem;
	align-items: center;
	gap: 0.125rem;
	padding: 0.25rem;
	box-sizing: border-box;
	border: 1px solid var(--color-divider);
	border-radius: 0.85rem;
	background: color-mix(in srgb, var(--color-raised-bg) 76%, transparent);
	box-shadow:
		var(--shadow-button),
		0 0.25rem 0.75rem rgb(0 0 0 / 20%);
	isolation: isolate;
	backdrop-filter: blur(18px) saturate(135%);
}

.home-floating-action {
	display: flex;
	width: 2rem;
	height: 2rem;
	align-items: center;
	justify-content: center;
	padding: 0;
	border: 0;
	border-radius: 9999px;
	background: transparent;
	color: var(--color-secondary);
	cursor: pointer;
	transition:
		background-color 120ms ease,
		color 120ms ease,
		filter 150ms ease,
		transform 150ms ease;
}

.home-floating-action:hover {
	background: var(--color-button-bg);
	color: var(--color-contrast);
}

.home-floating-action:active {
	transform: scale(0.96);
}

.home-floating-action:focus-visible,
.home-layout-switch:focus-visible {
	outline: none;
	box-shadow: 0 0 0 4px var(--color-brand-shadow);
}

.home-floating-action.is-active {
	background: var(--color-brand);
	color: var(--color-accent-contrast);
}

.home-floating-action :deep(svg) {
	width: 1rem;
	height: 1rem;
}

.home-floating-divider {
	width: 1px;
	height: 1.25rem;
	margin: 0 0.125rem;
	background: var(--color-divider);
}

.home-layout-switch {
	position: relative;
	display: grid;
	grid-template-columns: repeat(2, 2rem);
	align-items: center;
	width: 4.25rem;
	height: 2rem;
	margin: 0;
	padding: 0;
	border: 0;
	border-radius: 9999px;
	background: var(--color-button-bg);
	cursor: pointer;
	isolation: isolate;
	transition:
		filter 150ms ease,
		transform 150ms ease;
}

.home-layout-switch:hover:not(:disabled) {
	filter: brightness(var(--hover-brightness));
}

.home-layout-switch:active:not(:disabled) {
	transform: scale(0.97);
}

.home-layout-switch:disabled {
	cursor: not-allowed;
	opacity: 0.6;
}

.home-layout-switch-thumb {
	position: absolute;
	top: 0.125rem;
	left: 0.125rem;
	z-index: 0;
	width: 1.75rem;
	height: 1.75rem;
	border-radius: 9999px;
	background: var(--color-brand);
	transition: transform 180ms ease;
}

.home-layout-switch.is-minimal .home-layout-switch-thumb {
	transform: translateX(2rem);
}

.home-widget-layout-switch.is-free .home-layout-switch-thumb {
	transform: translateX(2rem);
}

.home-layout-switch-option {
	position: relative;
	z-index: 1;
	display: flex;
	width: 2rem;
	height: 1.75rem;
	align-items: center;
	justify-content: center;
	color: var(--color-secondary);
	transition: color 180ms ease;
}

.home-layout-switch-option :deep(svg) {
	width: 1rem;
	height: 1rem;
}

.home-layout-switch:not(.is-minimal) .home-layout-switch-information,
.home-layout-switch.is-minimal .home-layout-switch-minimal,
.home-widget-layout-switch:not(.is-free) .home-widget-layout-grid,
.home-widget-layout-switch.is-free .home-widget-layout-free {
	color: var(--color-accent-contrast);
}
</style>
