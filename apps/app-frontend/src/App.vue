<script setup lang="ts">
import { AuthFeature, TauriModrinthClient, VerboseLoggingFeature } from '@modrinth/api-client'
import {
	ChangeSkinIcon,
	CompassIcon,
	DownloadIcon,
	ExternalIcon,
	FlaskConicalIcon,
	HomeIcon,
	LeftArrowIcon,
	LibraryIcon,
	LogInIcon,
	LogOutIcon,
	PlusIcon,
	RefreshCwIcon,
	RightArrowIcon,
	SettingsIcon,
	SpinnerIcon,
	UserIcon,
	UsersIcon,
	WorldIcon,
} from '@modrinth/assets'
import {
	Admonition,
	Avatar,
	ButtonStyled,
	commonMessages,
	ContentInstallModal,
	ContentUpdaterModal,
	CreationFlowModal,
	defineMessages,
	I18nDebugPanel,
	LoadingBar,
	NotificationPanel,
	OverflowMenu,
	PopupNotificationPanel,
	provideModalBehavior,
	provideModrinthClient,
	provideNotificationManager,
	providePageContext,
	providePopupNotificationManager,
	useDebugLogger,
	useFormatBytes,
	useGlobalDrop,
	useVIntl,
} from '@modrinth/ui'
import ConfirmDropTypeModal from '@modrinth/ui/src/components/flows/drop/ConfirmDropTypeModal.vue'
import GenericContentInstallModal from '@modrinth/ui/src/components/flows/drop/GenericContentInstallModal.vue'
import LauncherImportModal from '@modrinth/ui/src/components/flows/drop/LauncherImportModal.vue'
import SymlinkMethodCards from '@modrinth/ui/src/components/flows/drop/SymlinkMethodCards.vue'
import { useInstanceContext } from '@modrinth/ui/src/composables/use-instance-context'
import { useQuery } from '@tanstack/vue-query'
import { getVersion } from '@tauri-apps/api/app'
import { convertFileSrc, invoke } from '@tauri-apps/api/core'
import { Effect, getCurrentWindow } from '@tauri-apps/api/window'
import { fetch as tauriFetch } from '@tauri-apps/plugin-http'
import { openUrl } from '@tauri-apps/plugin-opener'
import { type as getOsType } from '@tauri-apps/plugin-os'
import { saveWindowState, StateFlags } from '@tauri-apps/plugin-window-state'
import { computed, nextTick, onMounted, onUnmounted, provide, ref, watch } from 'vue'
import { type RouteLocationNormalizedLoaded, RouterView, useRoute, useRouter } from 'vue-router'

import AccountsCard from '@/components/ui/AccountsCard.vue'
import UpdateAnnouncementModal from '@/components/ui/announcement/UpdateAnnouncementModal.vue'
import AppActionBar from '@/components/ui/AppActionBar.vue'
import BlockEngineLogo from '@/components/ui/BlockEngineLogo.vue'
import Breadcrumbs from '@/components/ui/Breadcrumbs.vue'
import ErrorModal from '@/components/ui/ErrorModal.vue'
import AddServerToInstanceModal from '@/components/ui/install_flow/AddServerToInstanceModal.vue'
import UnknownPackWarningModal from '@/components/ui/install_flow/UnknownPackWarningModal.vue'
import MinecraftAuthErrorModal from '@/components/ui/minecraft-auth-error-modal/MinecraftAuthErrorModal.vue'
import MinecraftCrashModal from '@/components/ui/MinecraftCrashModal.vue'
import AppSettingsModal from '@/components/ui/modal/AppSettingsModal.vue'
import AuthGrantFlowWaitModal from '@/components/ui/modal/AuthGrantFlowWaitModal.vue'
import CommunityAnnouncementModal from '@/components/ui/modal/CommunityAnnouncementModal.vue'
import CurseForgeManualDownloadsModal from '@/components/ui/modal/CurseForgeManualDownloadsModal.vue'
import InstallToPlayModal from '@/components/ui/modal/InstallToPlayModal.vue'
import InstanceIconPickerModal from '@/components/ui/modal/InstanceIconPickerModal.vue'
import JavaDownloadConfirmationModal from '@/components/ui/modal/JavaDownloadConfirmationModal.vue'
import ModpackAlreadyInstalledModal from '@/components/ui/modal/ModpackAlreadyInstalledModal.vue'
import UpdateToPlayModal from '@/components/ui/modal/UpdateToPlayModal.vue'
import NavButton from '@/components/ui/NavButton.vue'
import NavRail from '@/components/ui/NavRail.vue'
import OnboardingOverlay from '@/components/ui/onboarding/OnboardingOverlay.vue'
import SplashScreen from '@/components/ui/SplashScreen.vue'
import WindowControls from '@/components/ui/WindowControls.vue'
import { useCheckDisableMouseover } from '@/composables/macCssFix.js'
import { minecraftLaunchErrorKey } from '@/composables/useMinecraftLaunchError'
import { useNetworkStatus } from '@/composables/useNetworkStatus'
import { AxolotlBrandConfig, config, getOfficialLabrinthBaseUrl } from '@/config'
import { debugAnalytics, initAnalytics, trackEvent } from '@/helpers/analytics'
import { check_reachable } from '@/helpers/auth.js'
import { get_user, get_version } from '@/helpers/cache.js'
import {
	type ClassificationResult,
	classifyDroppedItem,
	classifyDroppedItemWithExtraction,
	detectFileLock,
	extractModMetadata,
	extractZipToTemp,
	lookupModHash,
	type ModrinthLookupResult,
	removeTempDir,
	scanLauncherInstances,
	type ScanResult,
} from '@/helpers/drop'
import {
	command_listener,
	java_download_confirmation_listener,
	warning_listener,
} from '@/helpers/events.js'
import { import_instance } from '@/helpers/import.js'
import {
	install_create_modpack_instance,
	install_get_modpack_preview,
	wait_for_install_job,
} from '@/helpers/install'
import {
	add_project_from_path,
	check_symlink_capability,
	get as getInstance,
	import_world_save,
	list as listInstances,
	run,
} from '@/helpers/instance'
import { reconcileMojangAuthSourceAtStartup } from '@/helpers/mojang-auth'
import { cancelLogin, get as getCreds, login, logout } from '@/helpers/mr_auth.ts'
import { mergeUrlQuery, parseModrinthLink } from '@/helpers/project-links.ts'
import {
	get as getSettings,
	getCustomUpdateUrl,
	getUpdateSource,
	set as setSettings,
} from '@/helpers/settings.ts'
import { get_opening_command, initialize_state, set_discord_activity } from '@/helpers/state'
import {
	areUpdatesEnabled,
	checkAppUpdate,
	enqueueUpdateForInstallation,
	exportErrorLogs,
	getOS,
	getUpdateSize,
	isDev,
	isElevated,
	isNetworkMetered,
	setRestartAfterPendingUpdate,
} from '@/helpers/utils.js'
import { areLoadersCompatible, isVersionInRange } from '@/helpers/version-compatibility'
import { start_join_server, start_join_singleplayer_world } from '@/helpers/worlds.ts'
import i18n, { resolveInitialLocale } from '@/i18n.config'
import {
	appUpdateState,
	downloadAvailableAppUpdate,
	getNextAppUpdatePopupTime,
	installAvailableAppUpdate,
	markAppUpdateActionable,
	markAppUpdatePopupShown,
	openAppUpdateChangelog,
	setAppUpdateActions,
} from '@/providers/app-update.ts'
import { createContentInstall, provideContentInstall } from '@/providers/content-install'
import { createDownloadManager, provideDownloadManager } from '@/providers/download-manager'
import {
	provideAppUpdateDownloadProgress,
	subscribeToDownloadProgress,
} from '@/providers/download-progress.ts'
import { createServerInstall, provideServerInstall } from '@/providers/server-install'
import { setupProviders } from '@/providers/setup'
import { setupAuthProvider } from '@/providers/setup/auth'
import { setupLoadingStateProvider } from '@/providers/setup/loading-state'
import { useError } from '@/store/error.js'
import { useTheming } from '@/store/state'

import { get_available_capes, get_available_skins } from './helpers/skins'
import { AppNotificationManager } from './providers/app-notifications'
import { AppPopupNotificationManager } from './providers/app-popup-notifications'
import { ModrinthMirrorFallbackFeature } from './providers/modrinth-mirror-fallback'

const themeStore = useTheming()
const router = useRouter()
const route = useRoute()
const onSkinsPage = computed(() => route.path === '/skins')
const onSchematicWorkshopPage = computed(() => route.path === '/lab/schematic-preview')
const isSchematicFile = (path: string) => /\.(litematic|schematic|schem)$/i.test(path)
const APP_LEFT_NAV_WIDTH = '0px'
const APP_SIDEBAR_WIDTH = 300
const credentials = ref()
const sidebarToggled = ref(true)
const stopSidebarToggleWatch = watch(
	() => themeStore.toggleSidebar,
	(enabled) => {
		sidebarToggled.value = !enabled
	},
	{ immediate: true },
)
const forceSidebar = computed(
	() => route.path.startsWith('/browse') || route.path.startsWith('/project'),
)
const sidebarVisible = computed(() => sidebarToggled.value || forceSidebar.value)
const customBackgroundStyle = computed(() => {
	// A custom image would sit between the desktop and the UI, defeating the
	// transparent window entirely, so the two are mutually exclusive.
	if (themeStore.transparentBackground || !themeStore.customBackgroundPath) return undefined

	return {
		backgroundImage: `url("${convertFileSrc(themeStore.customBackgroundPath)}")`,
		filter: `blur(${themeStore.customBackgroundBlur}px)`,
		opacity: themeStore.customBackgroundOpacity / 100,
	}
})

const notificationManager = new AppNotificationManager()
provideNotificationManager(notificationManager)
const { handleError, addNotification } = notificationManager
const downloadManager = createDownloadManager(handleError)
provideDownloadManager(downloadManager)

const popupNotificationManager = new AppPopupNotificationManager()
providePopupNotificationManager(popupNotificationManager)
const { addPopupNotification } = popupNotificationManager

const appVersion = getVersion()
const tauriApiClient = new TauriModrinthClient({
	userAgent: async () => AxolotlBrandConfig.userAgent(await appVersion, await getOsType()),
	labrinthBaseUrl: config.labrinthBaseUrl,
	features: [
		...(AxolotlBrandConfig.capabilities.privateModrinthServices
			? [
					new AuthFeature({
						token: async () => (await getCreds())?.session,
					}),
				]
			: []),
		new ModrinthMirrorFallbackFeature(),
		new VerboseLoggingFeature(),
	],
})
provideModrinthClient(tauriApiClient)
providePageContext({
	hierarchicalSidebarAvailable: ref(true),
	showAds: ref(false),
	floatingActionBarOffsets: {
		left: ref(APP_LEFT_NAV_WIDTH),
		right: computed(() => (sidebarVisible.value ? `${APP_SIDEBAR_WIDTH}px` : '0px')),
	},
	featureFlags: {
		serverRamAsBytesAlwaysOn: computed(() =>
			themeStore.getFeatureFlag('server_ram_as_bytes_always_on'),
		),
	},
	openExternalUrl: (url) => openUrl(url),
})
provideModalBehavior({
	noblur: computed(() => !themeStore.advancedRendering),
})

const stateInitialization = initialize_state()
const {
	instanceIconPickerModal,
	installationModal,
	unknownPackWarningModal,
	fetchExistingInstanceNames,
	handleCreate,
	handleBrowseModpacks,
	searchModpacks,
	getProjectVersions,
	getLoaderManifest,
	installModpackFromPath,
	setModpackAlreadyInstalledModal,
	handleModpackDuplicateCreateAnyway,
	handleModpackDuplicateGoToInstance,
	fileDrop,
} = setupProviders(notificationManager, popupNotificationManager, stateInitialization)

const { browserOffline, offline, setNetworkReachable } = useNetworkStatus()

const showOnboarding = ref(false)
const onboardingMode = ref('main')
const onboardingSettings = ref(null)
const onboardingReplay = ref(false)
const navigationDockInteracting = ref(false)
let navigationDockHideTimer: ReturnType<typeof setTimeout> | null = null
const navigationBarLocked = computed(() =>
	themeStore.getFeatureFlag('block_engine_lock_navigation_bar'),
)
const navigationDockVisible = computed(
	() => navigationBarLocked.value || navigationDockInteracting.value || showOnboarding.value,
)

function clearNavigationDockHideTimer() {
	if (navigationDockHideTimer === null) return
	clearTimeout(navigationDockHideTimer)
	navigationDockHideTimer = null
}

function revealNavigationDock() {
	clearNavigationDockHideTimer()
	navigationDockInteracting.value = true
}

function scheduleNavigationDockHide() {
	if (navigationBarLocked.value || showOnboarding.value) return
	clearNavigationDockHideTimer()
	navigationDockHideTimer = setTimeout(() => {
		navigationDockInteracting.value = false
		navigationDockHideTimer = null
	}, 420)
}

onUnmounted(clearNavigationDockHideTimer)
const settingsModal = ref(null)
const nativeDecorations = ref(false)

const os = ref('')
const isDevEnvironment = ref(false)

/**
 * Acrylic is rendered by the Windows compositor behind the webview, so CSS
 * cannot clip it. Keep the native rounded frame and hide its border while the
 * CSS-drawn transparent-window border is active.
 */
async function applyWindowFrame() {
	if (os.value !== 'Windows') return

	try {
		await invoke('set_transparent_window_frame', {
			enabled: themeStore.transparentBackground,
		})
	} catch (error) {
		console.warn('Failed to update transparent window frame', error)
	}
}

watch(() => themeStore.transparentBackground, applyWindowFrame)

/**
 * The frosted glass has to come from the compositor: a webview cannot reach the
 * pixels behind its own window, so `backdrop-filter` can never blur the desktop.
 * Acrylic blurs whatever sits behind the window, matching what the transparency
 * already reveals; Mica would only sample the wallpaper and ignore other
 * windows. Linux exposes no window effects at all.
 */
async function applyWindowEffects() {
	if (os.value === 'Linux') return

	try {
		const window = getCurrentWindow()
		if (!themeStore.transparentBackground || !themeStore.transparentBackgroundBlur) {
			await window.clearEffects()
			return
		}

		await window.setEffects({
			effects: [os.value === 'MacOS' ? Effect.UnderWindowBackground : Effect.Acrylic],
		})
	} catch (error) {
		console.warn('Failed to update window effects', error)
	}
}

watch(
	() => [themeStore.transparentBackground, themeStore.transparentBackgroundBlur],
	applyWindowEffects,
)

const stateInitialized = ref(false)
const communityAnnouncementModal = ref()
const updateAnnouncementModal = ref()
const minecraftCrashModal = ref()
const javaDownloadConfirmationModal = ref()
const pendingUpdateAnnouncementVersion = ref(null)
const updateAnnouncementShowing = ref(false)

const isMaximized = ref(false)

const authUnreachableDebug = useDebugLogger('AuthReachableChecker')
const authServerQuery = useQuery({
	queryKey: ['authServerReachability'],
	enabled: computed(() => !browserOffline.value),
	queryFn: async () => {
		try {
			await check_reachable()
			setNetworkReachable(true)
			authUnreachableDebug('Auth servers are reachable')
			return true
		} catch (error) {
			setNetworkReachable(false)
			throw error
		}
	},
	refetchInterval: 5 * 60 * 1000, // 5 minutes
	retry: false,
	refetchOnWindowFocus: false,
})

const authUnreachable = computed(() => {
	if (!offline.value && authServerQuery.isError.value && !authServerQuery.isLoading.value) {
		console.warn('Failed to reach auth servers', authServerQuery.error.value)
		return true
	}
	return false
})

onMounted(async () => {
	await useCheckDisableMouseover()

	document.querySelector('body').addEventListener('click', handleClick)
	document.querySelector('body').addEventListener('auxclick', handleAuxClick)

	void warnIfRunningElevated()
})

onUnmounted(async () => {
	document.querySelector('body').removeEventListener('click', handleClick)
	document.querySelector('body').removeEventListener('auxclick', handleAuxClick)
	stopSidebarToggleWatch()
	clearDelayedUpdatePopup()
	await unlistenUpdateDownload?.()
	downloadManager.dispose()
})

const { formatMessage } = useVIntl()
const formatBytes = useFormatBytes()

async function warnIfRunningElevated() {
	if (await isElevated().catch(() => false)) {
		addNotification({
			title: formatMessage(messages.runningAsAdmin),
			type: 'warning',
			autoCloseMs: null,
		})
	}
}

async function onImportFileReceived({
	file: _file,
	filePath,
	source: _source,
}: {
	file: File | null
	filePath: string | null
	source: 'file-picker' | 'drag-drop'
}) {
	if (!filePath) return

	const fileName = filePath.split(/[/\\]/).pop() || 'file'

	// ── Hide creation modal first ──
	installationModal.value?.hide()

	// ── Show "Processing..." (matches drag-drop behavior) ──
	const processingNotify = addNotification({
		title: formatMessage(messages.dropProcessing, { name: fileName }),
		type: 'info',
		autoCloseMs: null,
	})

	try {
		// ── Classify the file (same entry point as drag-drop) ──
		const classification = await classifyDroppedItem(filePath)
		clearDropProcessingNotification()
		notificationManager.removeNotification(processingNotify.id)

		// ── Set drop state so handleDropConfirm can read it ──
		dropClassification.value = classification
		dropFilePath.value = classification.file_path ?? classification.base_path ?? filePath
		dropFileName.value = fileName

		// ── Unknown + nested archives → confirm unpacking first ──
		if (
			classification.item_type === 'unknown' &&
			classification.reason?.toLowerCase().includes('nested')
		) {
			showNestedUnpackPrompt(classification)
			return
		}

		// ── Unknown + extraction → force analysis prompt ──
		if (
			classification.item_type === 'unknown' &&
			classification.reason?.toLowerCase().includes('extraction')
		) {
			showForceAnalysisPrompt(classification)
			return
		}

		// ── Unknown (no extraction) → error ──
		if (classification.item_type === 'unknown') {
			addNotification({
				title: formatMessage(messages.dropUnknownTitle),
				text: unknownReasonMessage(classification.reason),
				type: 'error',
			})
			return
		}

		// ── Known types → show the same confirm modal as drag-drop ──
		confirmDropModal.value?.show()
	} catch (e) {
		notificationManager.removeNotification(processingNotify?.id)
		addNotification({
			title: formatMessage(messages.dropProcessFailedTitle),
			text: e instanceof Error ? e.message : String(e),
			type: 'error',
		})
	}
}

const messages = defineMessages({
	updateInstalledToastTitle: {
		id: 'app.update.complete-toast.title',
		defaultMessage: 'Version {version} was successfully installed!',
	},
	updateInstalledToastText: {
		id: 'app.update.complete-toast.text',
		defaultMessage: 'Click here to view the changelog.',
	},
	authUnreachableHeader: {
		id: 'app.auth-servers.unreachable.header',
		defaultMessage: 'Cannot reach authentication servers',
	},
	authUnreachableBody: {
		id: 'app.auth-servers.unreachable.body',
		defaultMessage:
			'Minecraft authentication servers may be down right now. Check your internet connection and try again later.',
	},
	runningAsAdmin: {
		id: 'app.warning.running-as-admin',
		defaultMessage:
			'方块引擎正在以管理员身份运行。此模式下无法拖放导入文件，请以普通用户身份重新启动。',
	},
	restarting: {
		id: 'app.restarting',
		defaultMessage: 'Restarting...',
	},
	home: {
		id: 'app.navigation.home',
		defaultMessage: 'Home',
	},
	worlds: {
		id: 'app.navigation.worlds',
		defaultMessage: 'Worlds',
	},
	discoverContent: {
		id: 'app.navigation.discover-content',
		defaultMessage: 'Discover content',
	},
	skinSelector: {
		id: 'app.navigation.skin-selector',
		defaultMessage: 'Skin selector',
	},
	library: {
		id: 'app.navigation.library',
		defaultMessage: 'Library',
	},
	multiplayer: {
		id: 'app.navigation.multiplayer',
		defaultMessage: 'Multiplayer',
	},
	downloads: {
		id: 'app.navigation.downloads',
		defaultMessage: 'Downloads',
	},
	lab: {
		id: 'app.navigation.lab',
		defaultMessage: 'Lab',
	},
	createInstance: {
		id: 'app.navigation.create-instance',
		defaultMessage: 'Create new instance',
	},
	signedInAs: {
		id: 'app.account.signed-in-as',
		defaultMessage: 'Signed in as',
	},
	playingAs: {
		id: 'app.minecraft.playing-as',
		defaultMessage: 'Playing as',
	},
	warning: {
		id: 'app.notification.warning',
		defaultMessage: 'Warning',
	},
	exportErrorLogs: {
		id: 'app.notification.export-error-logs',
		defaultMessage: 'Export error logs',
	},

	// ── Drop / import notification messages ──
	dropOverlayTitle: {
		id: 'app.drop.overlay-title',
		defaultMessage: 'Drop to import',
	},
	dropOverlaySubtitle: {
		id: 'app.drop.overlay-subtitle',
		defaultMessage: 'Release to analyze',
	},
	dropProcessing: {
		id: 'app.drop.processing',
		defaultMessage: 'Processing {name}...',
	},
	dropMultipleFilesTitle: {
		id: 'app.drop.error.multiple-files-title',
		defaultMessage: 'Cannot import multiple files',
	},
	dropMultipleFilesText: {
		id: 'app.drop.error.multiple-files-text',
		defaultMessage: 'Please drop one file at a time.',
	},
	dropShortcutFailedTitle: {
		id: 'app.drop.error.shortcut-title',
		defaultMessage: 'Shortcut resolution failed',
	},
	dropShortcutFailedText: {
		id: 'app.drop.error.shortcut-text',
		defaultMessage: 'Could not resolve the shortcut target.',
	},
	dropUnknownTitle: {
		id: 'app.drop.error.unknown-title',
		defaultMessage: 'Unknown file type',
	},
	dropUnknownText: {
		id: 'app.drop.error.unknown-text',
		defaultMessage: 'Could not determine what kind of file this is.',
	},
	dropUnknownDepthText: {
		id: 'app.drop.error.unknown-depth-text',
		defaultMessage:
			'The archive is nested too deeply to analyze. Unpack it to a folder and try again.',
	},
	dropUnknownEncryptedText: {
		id: 'app.drop.error.unknown-encrypted-text',
		defaultMessage: 'The archive contains encrypted files and cannot be analyzed.',
	},
	dropNestedUnpackTitle: {
		id: 'app.drop.nested-unpack-title',
		defaultMessage: 'Nested archives detected',
	},
	dropNestedUnpackText: {
		id: 'app.drop.nested-unpack-text',
		defaultMessage:
			'This archive contains nested archives ({size}) that must be unpacked to analyze. This may take some time. Continue?',
	},
	dropNestedUnpackButton: {
		id: 'app.drop.nested-unpack-button',
		defaultMessage: 'Continue analysis',
	},
	dropErrorTitle: {
		id: 'app.drop.error.title',
		defaultMessage: 'Drop error',
	},
	dropWorldImportedTitle: {
		id: 'app.drop.world-imported-title',
		defaultMessage: 'World imported',
	},
	dropWorldImportedText: {
		id: 'app.drop.world-imported-text',
		defaultMessage: 'World save has been imported successfully.',
	},
	dropContentInstalledTitle: {
		id: 'app.drop.content-installed-title',
		defaultMessage: 'Content installed',
	},
	dropContentInstalledText: {
		id: 'app.drop.content-installed-text',
		defaultMessage: 'File has been installed to the instance.',
	},
	dropInstallFailedTitle: {
		id: 'app.drop.install-failed-title',
		defaultMessage: 'Installation failed',
	},
	dropInstanceImportedTitle: {
		id: 'app.drop.instance-imported-title',
		defaultMessage: 'Instance imported',
	},
	dropInstanceImportedText: {
		id: 'app.drop.instance-imported-text',
		defaultMessage: '{name} imported successfully.',
	},
	dropImportFailedTitle: {
		id: 'app.drop.import-failed-title',
		defaultMessage: 'Import failed',
	},
	dropImportFailedText: {
		id: 'app.drop.import-failed-text',
		defaultMessage: 'Failed to import {name}: {error}',
	},
	dropNoInstances: {
		id: 'app.drop.no-instances',
		defaultMessage: 'No instances found',
	},
	dropScanning: {
		id: 'app.drop.scanning',
		defaultMessage: 'Scanning for instances',
	},
	dropScanFailed: {
		id: 'app.drop.scan-failed',
		defaultMessage: 'Failed to scan for instances',
	},
	dropExtractFailed: {
		id: 'app.drop.extract-failed',
		defaultMessage: 'Failed to extract archive',
	},
	dropProcessFailedTitle: {
		id: 'app.drop.process-failed-title',
		defaultMessage: 'Failed to process file',
	},
	dropTemporaryFileTitle: {
		id: 'app.drop.temporary-file-title',
		defaultMessage: 'Temporary file detected',
	},
	dropTemporaryFileText: {
		id: 'app.drop.temporary-file-text',
		defaultMessage:
			'The file "{file}" appears to be a temporary copy. Try dragging the file from its original folder instead of from a browser, archive, or cloud storage.',
	},
	dropImportProgressTitle: {
		id: 'app.drop.import-progress-title',
		defaultMessage: 'Importing instances…',
	},
	dropImportProgressText: {
		id: 'app.drop.import-progress-text',
		defaultMessage: '{current} / {total} instances imported',
	},
	dropImportCompletedTitle: {
		id: 'app.drop.import-completed-title',
		defaultMessage: 'Import completed',
	},
	dropImportCompletedText: {
		id: 'app.drop.import-completed-text',
		defaultMessage: 'Successfully imported {count} instances',
	},
	dropImportCompletedPartialText: {
		id: 'app.drop.import-completed-partial-text',
		defaultMessage: 'Imported {completed} of {total} instances ({failed} failed)',
	},

	dropModpackInstallFailed: {
		id: 'app.drop.modpack-install-failed',
		defaultMessage: 'Failed to install modpack',
	},

	dropUnknownForceAnalysisTitle: {
		id: 'app.drop.unknown-force-analysis-title',
		defaultMessage: 'Unable to identify file type',
	},
	dropUnknownForceAnalysisText: {
		id: 'app.drop.unknown-force-analysis-text',
		defaultMessage:
			'This archive needs to be extracted and deeply analyzed to determine its content type. This may take a while. Force analysis?',
	},
	dropUnknownForceAnalysisButton: {
		id: 'app.drop.unknown-force-analysis-button',
		defaultMessage: 'Force analysis',
	},
	dropUnknownForceAnalyzing: {
		id: 'app.drop.unknown-force-analyzing',
		defaultMessage: 'Force analyzing archive...',
	},
	dropUnknownForceAnalysisFailedTitle: {
		id: 'app.drop.unknown-force-analysis-failed-title',
		defaultMessage: 'Analysis failed',
	},
	dropUnknownForceAnalysisFailedText: {
		id: 'app.drop.unknown-force-analysis-failed-text',
		defaultMessage: 'Could not identify the file type even after deep analysis.',
	},

	dropInstallModTitle: {
		id: 'app.drop.mod-compatibility-title',
		defaultMessage: 'Version Mismatch',
	},
	dropInstallModWarning: {
		id: 'app.drop.mod-compatibility-warning',
		defaultMessage:
			'This mod targets {modVersion} ({modLoader}), but the instance is {instVersion} ({instLoader}).',
	},
})

function getErrorNotificationDetails(notification) {
	const details = [notification.title, notification.text, notification.errorCode].filter(Boolean)
	if (notification.supportData) {
		details.push(JSON.stringify(notification.supportData, null, 2))
	}
	return details.join('\n\n')
}

async function exportNotificationErrorLogs(notification) {
	try {
		await exportErrorLogs(getErrorNotificationDetails(notification))
	} catch (error) {
		handleError(error)
	}
}

async function setupApp() {
	const initialSettings = await getSettings()
	let updateSettingsChanged = false
	if (initialSettings.auto_download_updates !== false) {
		initialSettings.auto_download_updates = false
		updateSettingsChanged = true
	}
	if (initialSettings.pending_update_toast_for_version !== null) {
		initialSettings.pending_update_toast_for_version = null
		updateSettingsChanged = true
	}
	if (updateSettingsChanged) await setSettings(initialSettings)
	await downloadManager.start()
	const {
		native_decorations,
		theme,
		accent_color,
		locale,
		telemetry,
		collapsed_navigation,
		hide_nametag_skins_page,
		advanced_rendering,
		onboarded,
		default_page,
		toggle_sidebar,
		custom_background_path,
		custom_background_blur,
		custom_background_opacity,
		transparent_background,
		transparent_background_opacity,
		transparent_background_blur,
		sidebar_instance_count,
		auto_hide_downloads_button,
		home_layout,
		minimal_home_instance_id,
		developer_mode,
		feature_flags,
		pending_update_toast_for_version,
	} = initialSettings

	// Initialize locale from saved settings
	if (locale) {
		i18n.global.locale.value = locale
	} else {
		const resolvedLocale = resolveInitialLocale(navigator.languages)
		i18n.global.locale.value = resolvedLocale
		initialSettings.locale = resolvedLocale
		await setSettings(initialSettings)
	}

	const defaultPageRoutes = {
		Home: '/',
		DiscoverContent: '/browse/modpack',
		Library: '/library',
	}
	const defaultPageRoute = offline.value ? '/library' : defaultPageRoutes[default_page]
	if (defaultPageRoute && defaultPageRoute !== '/') await router.push(defaultPageRoute)

	os.value = await getOS()
	const dev = await isDev()
	isDevEnvironment.value = dev
	const version = await getVersion()
	pendingUpdateAnnouncementVersion.value = pending_update_toast_for_version
	if (!onboarded && route.path !== '/') await router.replace('/')
	showOnboarding.value = !onboarded
	onboardingSettings.value = initialSettings

	nativeDecorations.value = native_decorations
	if (os.value !== 'MacOS') await getCurrentWindow().setDecorations(native_decorations)

	themeStore.setThemeState(theme)
	themeStore.setAccentColor(accent_color)
	themeStore.collapsedNavigation = collapsed_navigation
	themeStore.advancedRendering = advanced_rendering
	themeStore.hideNametagSkinsPage = hide_nametag_skins_page
	themeStore.toggleSidebar = toggle_sidebar
	themeStore.customBackgroundPath = custom_background_path
	themeStore.customBackgroundBlur = custom_background_blur
	themeStore.customBackgroundOpacity = custom_background_opacity
	themeStore.transparentBackground = transparent_background
	themeStore.transparentBackgroundOpacity = transparent_background_opacity
	themeStore.transparentBackgroundBlur = transparent_background_blur
	themeStore.setTransparentBackgroundClass()
	await applyWindowFrame()
	await applyWindowEffects()
	themeStore.sidebarInstanceCount = sidebar_instance_count
	themeStore.autoHideDownloadsButton = auto_hide_downloads_button
	themeStore.homeLayout = home_layout
	themeStore.minimalHomeInstanceId = minimal_home_instance_id
	themeStore.devMode = developer_mode
	themeStore.featureFlags = feature_flags
	stateInitialized.value = true
	void reconcileMojangAuthSourceAtStartup().catch(handleError)

	isMaximized.value = await getCurrentWindow().isMaximized()

	await getCurrentWindow().onResized(async () => {
		isMaximized.value = await getCurrentWindow().isMaximized()
	})

	if (telemetry) {
		initAnalytics()
		if (dev) debugAnalytics()
		trackEvent('Launched', { version, dev, onboarded })
	}

	if (!dev) document.addEventListener('contextmenu', (event) => event.preventDefault())

	const osType = await getOsType()
	if (osType === 'macos') {
		document.getElementsByTagName('html')[0].classList.add('mac')
	} else {
		document.getElementsByTagName('html')[0].classList.add('windows')
	}

	await warning_listener(async (e) => {
		if (e.kind === 'minecraft_crash') {
			await minecraftCrashModal.value?.handleWarning(e)
			return
		}

		addNotification({
			title: formatMessage(messages.warning),
			text: e.message,
			type: 'warn',
		})
	})
	await java_download_confirmation_listener((request) => {
		javaDownloadConfirmationModal.value?.show(request)
	})

	get_opening_command().then(handleCommand)
	fetchCredentials()

	try {
		const skins = (await get_available_skins()) ?? []
		const capes = (await get_available_capes()) ?? []
		const { generateSkinPreviews } = await import('./helpers/rendering/skin-preview-renderer')
		generateSkinPreviews(skins, capes)
	} catch (error) {
		console.warn('Failed to generate skin previews in app setup.', error)
	}
}

function startOnboarding(mode = 'main') {
	onboardingReplay.value = false
	onboardingMode.value = mode
	showOnboarding.value = true
}

async function replayOnboarding(mode) {
	onboardingReplay.value = true
	onboardingMode.value = mode
	settingsModal.value?.hide()
	if (mode === 'main') await router.replace('/')
	showOnboarding.value = true
}

async function finishOnboarding() {
	const wasReplay = onboardingReplay.value
	const settings = onboardingSettings.value ?? (await getSettings())
	if (!onboardingReplay.value) {
		if (onboardingMode.value === 'instance') {
			settings.onboarding_instance_tour_completed = true
		} else if (onboardingMode.value === 'main') {
			settings.onboarded = true
			settings.onboarding_version = 1
		}
		await setSettings(settings)
		onboardingSettings.value = settings
	}
	showOnboarding.value = false
	onboardingReplay.value = false
	if (!wasReplay) await scheduleStartupDialogs()
}

async function skipOnboarding() {
	await finishOnboarding()
}

function closeOnboardingSettings() {
	settingsModal.value?.hide()
}

async function handleUpdateAnnouncementClosed(version) {
	if (pendingUpdateAnnouncementVersion.value !== version) return

	const settings = await getSettings()
	if (settings.pending_update_toast_for_version === version) {
		settings.pending_update_toast_for_version = null
		await setSettings(settings)
	}
	pendingUpdateAnnouncementVersion.value = null
	updateAnnouncementShowing.value = false
	await new Promise((resolve) => setTimeout(resolve, 350))
	await scheduleStartupDialogs()
}

async function scheduleStartupDialogs() {
	if (!stateInitialized.value || showOnboarding.value || updateAnnouncementShowing.value) return

	if (pendingUpdateAnnouncementVersion.value && updateAnnouncementModal.value) {
		updateAnnouncementShowing.value = true
		settingsModal.value?.hide()
		await nextTick()
		updateAnnouncementModal.value.show(pendingUpdateAnnouncementVersion.value)
		return
	}

	communityAnnouncementModal.value?.showIfNeeded()
}

provide('replayOnboarding', replayOnboarding)
provide(
	minecraftLaunchErrorKey,
	async (launchError, payload) =>
		(await minecraftCrashModal.value?.handleLaunchError(launchError, payload)) ?? false,
)
provide('previewMinecraftCrashModal', () => minecraftCrashModal.value?.showPreview())
provide('chooseImportMethod', chooseImportMethod)
provide('previewUpdateAnnouncement', (version = null) => {
	const previewVersion = version ?? pendingUpdateAnnouncementVersion.value
	if (previewVersion) updateAnnouncementModal.value?.show(previewVersion)
})

const stateFailed = ref(false)
stateInitialization
	.then(() => {
		setupApp().catch((err) => {
			stateFailed.value = true
			console.error(err)
			error.showError(err, null, false, 'state_init')
		})
	})
	.catch((err) => {
		stateFailed.value = true
		console.error('Failed to initialize app', err)
		error.showError(err, null, false, 'state_init')
	})

const handleClose = async () => {
	await saveWindowState(StateFlags.ALL)
	await getCurrentWindow().close()
}

const loading = setupLoadingStateProvider()
loading.setEnabled(false)
let initialLoadToken = loading.begin()
let routerToken = null
let suspenseToken = null
let lastDiscordActivity = null
let discordActivityUpdate = Promise.resolve()

let suspensePending = false

const sidebarOverlayScrollbarsOptions = Object.freeze({
	overflow: {
		x: 'hidden',
		y: 'scroll',
	},
})

router.beforeEach(() => {
	suspensePending = false
	if (routerToken) loading.end(routerToken)
	routerToken = loading.begin()
})

function syncDiscordActivity(to: RouteLocationNormalizedLoaded) {
	const activity =
		typeof to.meta.discordActivity === 'string' ? to.meta.discordActivity : 'Idling...'
	if (activity === lastDiscordActivity) return

	lastDiscordActivity = activity
	discordActivityUpdate = discordActivityUpdate
		.then(() => set_discord_activity(activity))
		.catch((error) => {
			if (lastDiscordActivity === activity) lastDiscordActivity = null
			console.error('Failed to update Discord activity', error)
		})
}

router.afterEach((to, from, failure) => {
	trackEvent('PageView', {
		path: to.path,
		fromPath: from.path,
		failed: failure,
	})
	if (!failure && stateInitialized.value) syncDiscordActivity(to)
	setTimeout(() => {
		if (!suspensePending && stateInitialized.value) {
			if (initialLoadToken) {
				loading.end(initialLoadToken)
				initialLoadToken = null
			}
			if (routerToken) {
				loading.end(routerToken)
				routerToken = null
			}
		}
	}, 100)
})

function onSuspensePending() {
	suspensePending = true
	if (suspenseToken) loading.end(suspenseToken)
	suspenseToken = loading.begin()
}

function onSuspenseResolve() {
	if (suspenseToken) {
		loading.end(suspenseToken)
		suspenseToken = null
	}
	if (routerToken) {
		loading.end(routerToken)
		routerToken = null
	}
}

watch(
	stateInitialized,
	(ready) => {
		if (ready) {
			syncDiscordActivity(router.currentRoute.value)
			if (initialLoadToken) {
				loading.end(initialLoadToken)
				initialLoadToken = null
			}
			if (routerToken) {
				loading.end(routerToken)
				routerToken = null
			}
			void scheduleStartupDialogs()
		}
	},
	{ flush: 'post' },
)

watch(offline, (isOffline) => {
	if (isOffline && (route.path.startsWith('/browse') || route.path.startsWith('/project'))) {
		void router.push('/library')
	}
})

watch(
	() => route.path,
	(path) => {
		if (
			path.startsWith('/instance/') &&
			onboardingSettings.value?.onboarded &&
			!onboardingSettings.value?.onboarding_instance_tour_completed &&
			!showOnboarding.value
		) {
			startOnboarding('instance')
		}
	},
)

const error = useError()
error.setMinecraftLaunchErrorHandler((launchError, context) => {
	if (!minecraftCrashModal.value?.isLaunchFailure(launchError) || !context?.instanceId) return false
	void minecraftCrashModal.value.handleLaunchError(launchError, {
		instance_id: context.instanceId,
		instance_name: 'Minecraft',
	})
	return true
})
const errorModal = ref()
const minecraftAuthErrorModal = ref()

const contentInstall = createContentInstall({ router, handleError, addNotification })
provideContentInstall(contentInstall)
const {
	instances: contentInstallInstances,
	compatibleLoaders: contentInstallLoaders,
	gameVersions: contentInstallGameVersions,
	loading: contentInstallLoading,
	defaultTab: contentInstallDefaultTab,
	preferredLoader: contentInstallPreferredLoader,
	preferredGameVersion: contentInstallPreferredGameVersion,
	releaseGameVersions: contentInstallReleaseGameVersions,
	projectInfo: contentInstallProjectInfo,
	symlinkTarget: contentInstallSymlinkTarget,
	handleInstallToInstance,
	handleCreateAndInstall,
	handleNavigate: handleContentInstallNavigate,
	handleCancel: handleContentInstallCancel,
	setContentInstallModal,
	setModpackAlreadyInstalledModal: setContentInstallModpackAlreadyInstalledModal,
	handleModpackDuplicateCreateAnyway: handleContentInstallModpackDuplicateCreateAnyway,
	handleModpackDuplicateGoToInstance: handleContentInstallModpackDuplicateGoToInstance,
	setCurseForgeManualDownloadsModal: setContentInstallCurseForgeManualDownloadsModal,
	handleCurseForgeManualDownloadsImported: handleContentInstallCurseForgeManualDownloadsImported,
	setIncompatibilityWarningModal: setContentIncompatibilityWarningModal,
	incompatibilityWarningVersions: contentInstallIncompatibilityWarningVersions,
	incompatibilityWarningCurrentGameVersion: contentInstallIncompatibilityWarningCurrentGameVersion,
	incompatibilityWarningCurrentLoader: contentInstallIncompatibilityWarningCurrentLoader,
	incompatibilityWarningProjectType: contentInstallIncompatibilityWarningProjectType,
	incompatibilityWarningProjectIconUrl: contentInstallIncompatibilityWarningProjectIconUrl,
	incompatibilityWarningProjectName: contentInstallIncompatibilityWarningProjectName,
	incompatibilityWarningMessage: contentInstallIncompatibilityWarningMessage,
	incompatibilityWarningInstalling: contentInstallIncompatibilityWarningInstalling,
	handleIncompatibilityWarningInstall: handleContentInstallIncompatibilityWarningInstall,
	handleIncompatibilityWarningCancel: handleContentInstallIncompatibilityWarningCancel,
} = contentInstall

/**
 * Handles @update from ContentUpdaterModal (incompatibility-warning mode).
 * In drag & drop mode (pendingDropIncompatibility set), installs the local file.
 * In normal content-install mode, delegates to the provider handler.
 */
async function handleIncompatibilityWarningUpdate(
	version: Labrinth.Versions.v2.Version,
	event: MouseEvent,
) {
	const pending = pendingDropIncompatibility.value
	if (pending) {
		pendingDropIncompatibility.value = null
		const projectType = contentFileProjectTypeMap[pending.type]
		try {
			await add_project_from_path(pending.instId, pending.filePath, projectType)
			addNotification({
				title: formatMessage(messages.dropContentInstalledTitle),
				text: formatMessage(messages.dropContentInstalledText),
				type: 'success',
			})
		} catch (e) {
			addNotification({
				title: formatMessage(messages.dropInstallFailedTitle),
				text: e instanceof Error ? e.message : typeof e === 'string' ? e : JSON.stringify(e),
				type: 'error',
			})
		}
		return
	}
	await handleContentInstallIncompatibilityWarningInstall(version, event)
}

/**
 * Handles @cancel from ContentUpdaterModal. Clears drag & drop state if set.
 */
function handleIncompatibilityWarningCancel() {
	pendingDropIncompatibility.value = null
	handleContentInstallIncompatibilityWarningCancel()
}

/**
 * Handles @searchCompat from ContentUpdaterModal simplified warning mode.
 * Navigates to the Modrinth project page or browse/search page.
 */
function handleDropInstallSearchCompat() {
	const pending = pendingDropIncompatibility.value
	if (!pending) return
	const searchName = pending.meta?.name ?? pending.meta?.mod_id ?? 'mod'
	const searchUrl = pending.modrinthLookup
		? `/project/${pending.modrinthLookup.project_id}`
		: `/browse/mod?q=${encodeURIComponent(searchName)}&i=${pending.instId}`
	pendingDropIncompatibility.value = null
	router.push(searchUrl)
}

const serverInstall = createServerInstall({ router, handleError, popupNotificationManager })
provideServerInstall(serverInstall)
const {
	setInstallToPlayModal: setServerInstallToPlayModal,
	setUpdateToPlayModal: setServerUpdateToPlayModal,
	setAddServerToInstanceModal: setServerAddServerToInstanceModal,
	playServerProject,
	symlinkTarget: addServerSymlinkTarget,
} = serverInstall

const modInstallModal = ref()
const modpackAlreadyInstalledModal = ref()
const contentInstallModpackAlreadyInstalledModal = ref()
const contentInstallCurseForgeManualDownloadsModal = ref()
const addServerToInstanceModal = ref()
const incompatibilityWarningModal = ref()
const installToPlayModal = ref()
const updateToPlayModal = ref()

const modrinthLoginFlowWaitModal = ref()

const confirmDropModal = ref<InstanceType<typeof ConfirmDropTypeModal> | null>(null)
const dropClassification = ref<ClassificationResult | null>(null)
const dropFileName = ref('')
const dropFilePath = ref('')
const lastDroppedPath = ref('')

const { isInInstance, instanceId } = useInstanceContext()
const genericInstallModal = ref<InstanceType<typeof GenericContentInstallModal> | null>(null)
const launcherImportModal = ref<InstanceType<typeof LauncherImportModal> | null>(null)
const symlinkCardsModal = ref<InstanceType<typeof SymlinkMethodCards> | null>(null)
const contentFileProjectTypeMap: Record<string, ContentFileProjectType | undefined> = {
	mod: 'mod',
	resource_pack: 'resourcepack',
	shader_pack: 'shaderpack',
	litematic: 'schematic',
	schematic: 'schematic',
}

const scanningInstances = ref(false)
const pendingInstall = ref<{ type: string; filePath: string } | null>(null)
const pendingDropIncompatibility = ref<{
	filePath: string
	instId: string
	type: string
	instVersion: string | undefined
	instLoader: string | undefined
	meta: { name?: string; mod_id?: string } | null
	modrinthLookup: ModrinthLookupResult | null
} | null>(null)
const selectedInstances = ref<
	Array<{ launcherType: string; basePath: string; name: string; path: string }>
>([])
const currentImportContext = ref<{ launcherType: string; basePath: string } | null>(null)
const launcherZipTempDir = ref<string | null>(null)

const dropDebug = useDebugLogger('DropFlow')

const dropProcessingNotificationId = ref<number | null>(null)

const { isDragging, isProcessing } = useGlobalDrop(
	{
		classifyFile: async (path) => {
			lastDroppedPath.value = path
			if (onSkinsPage.value) {
				return { item_type: 'unknown' as const, file_path: path, reason: 'skipped' }
			}
			if (onSchematicWorkshopPage.value && isSchematicFile(path)) {
				return { item_type: 'unknown' as const, file_path: path, reason: 'skipped' }
			}
			return classifyDroppedItem(path)
		},
		onClassifyStart: (fileName) => {
			if (onSkinsPage.value) return
			if (onSchematicWorkshopPage.value && isSchematicFile(fileName)) return
			// Immediate feedback when a file is dropped — show a notification
			// with the file name before classification even begins.
			dropProcessingNotificationId.value = addNotification({
				title: formatMessage(messages.dropProcessing, { name: fileName }),
				type: 'info',
				autoCloseMs: null,
			}).id
		},
		onImportStart: (type, classification) => {
			if (type === 'unknown' && classification?.reason === 'skipped') return
			dropClassification.value = classification
			// Unknown results carry no file_path; fall back to the raw dropped
			// path so force-analysis / nested-unpack prompts can still act.
			dropFilePath.value =
				classification.file_path ?? classification.base_path ?? lastDroppedPath.value
			dropFileName.value =
				classification.file_path?.split(/[/\\]/).pop() ??
				classification.base_path?.split(/[/\\]/).pop() ??
				(lastDroppedPath.value.split(/[/\\]/).pop() || 'file')

			if (type === 'unknown' && classification?.reason?.toLowerCase().includes('nested')) {
				clearDropProcessingNotification()
				showNestedUnpackPrompt(classification)
				return
			}

			if (type === 'unknown' && classification?.reason?.toLowerCase().includes('extraction')) {
				clearDropProcessingNotification()
				showForceAnalysisPrompt(classification)
				return
			}

			if (type === 'unknown') {
				clearDropProcessingNotification()
				const unknownFile =
					classification?.file_path?.split(/[/\\]/).pop() ??
					classification?.base_path?.split(/[/\\]/).pop() ??
					''

				// .tmp files are OS-level temp copies from drag-and-drop (browser, archive, etc.)
				const isTempFile = unknownFile.startsWith('.tmp') || unknownFile.startsWith('tmp')
				if (isTempFile) {
					addNotification({
						title: formatMessage(messages.dropTemporaryFileTitle),
						text: formatMessage(messages.dropTemporaryFileText, {
							file: unknownFile,
						}),
						type: 'warning',
					})
				} else {
					addNotification({
						title: formatMessage(messages.dropUnknownTitle),
						text: unknownReasonMessage(classification?.reason),
						type: 'error',
					})
				}
				return
			}

			confirmDropModal.value?.show()
		},
		onImportEnd: () => {},
		onError: (reason) => {
			clearDropProcessingNotification()

			if (reason === 'multiple-files') {
				addNotification({
					title: formatMessage(messages.dropMultipleFilesTitle),
					text: formatMessage(messages.dropMultipleFilesText),
					type: 'error',
				})
			} else if (reason === 'shortcut-exceeded') {
				addNotification({
					title: formatMessage(messages.dropShortcutFailedTitle),
					text: formatMessage(messages.dropShortcutFailedText),
					type: 'error',
				})
			} else if (reason === 'unknown') {
				addNotification({
					title: formatMessage(messages.dropUnknownTitle),
					text: formatMessage(messages.dropUnknownText),
					type: 'error',
				})
			} else {
				addNotification({
					title: formatMessage(messages.dropErrorTitle),
					text: reason,
					type: 'error',
				})
			}
		},
	},
	fileDrop,
)

function clearDropProcessingNotification() {
	if (dropProcessingNotificationId.value !== null) {
		notificationManager.removeNotification(dropProcessingNotificationId.value)
		dropProcessingNotificationId.value = null
	}
}

function handleDropCancel() {
	clearDropProcessingNotification()
	dropClassification.value = null
}

async function handleDropConfirm(type: string) {
	const classification = dropClassification.value
	dropClassification.value = null
	confirmDropModal.value?.hide()

	dropDebug('handleDropConfirm: entry', {
		type,
		classification_item_type: classification?.item_type,
		file_path: classification?.file_path,
	})

	const isLauncherImport =
		classification?.item_type === 'launcher' || classification?.item_type === 'hmcl_launcher'

	if (!isLauncherImport && !classification?.file_path && !dropFilePath.value) {
		dropDebug(
			'handleDropConfirm: no filePath available (classification and dropFilePath both empty), aborting',
		)
		return
	}

	const filePath = classification?.file_path ?? dropFilePath.value
	const fileName =
		filePath?.split(/[/\\]/).pop() ?? classification.base_path?.split(/[/\\]/).pop() ?? 'file'
	dropDebug('handleDropConfirm: routing decision', {
		type,
		isLauncherImport,
		item_type: classification?.item_type,
	})

	if (type === 'dot_minecraft') {
		dropDebug('handleDropConfirm: .minecraft folder branch', {
			dropFilePath: dropFilePath.value,
		})
		if (!dropFilePath.value) {
			dropDebug('handleDropConfirm: dot_minecraft — no dropFilePath, aborting')
			return
		}
		// Treat the .minecraft folder path as a vanilla-style instance source
		// and scan it for importable instances
		currentImportContext.value = { launcherType: 'Generic', basePath: dropFilePath.value }
		scanningInstances.value = true
		let results: ScanResult[]
		try {
			results = await scanLauncherInstances('Generic', dropFilePath.value)
		} catch (error) {
			currentImportContext.value = null
			dropDebug('handleDropConfirm: .minecraft scan failed', error)
			addNotification({ title: formatMessage(messages.dropScanFailed), type: 'error' })
			return
		} finally {
			scanningInstances.value = false
		}
		const totalInstances = results.reduce((s, r) => s + r.instances.length, 0)
		dropDebug('handleDropConfirm: .minecraft scan result', { totalInstances, results })

		if (totalInstances === 0) {
			currentImportContext.value = null
			dropDebug('handleDropConfirm: no instances found in .minecraft folder')
			addNotification({ title: formatMessage(messages.dropNoInstances), type: 'warning' })
			return
		}

		if (totalInstances === 1 && results[0]?.instances[0]) {
			const single = results[0].instances[0]
			dropDebug('handleDropConfirm: single instance from .minecraft, showing symlink modal', {
				name: single.name,
				path: single.path,
			})
			selectedInstances.value = [
				{
					launcherType: 'Generic',
					basePath: dropFilePath.value,
					name: single.name,
					path: single.path,
				},
			]
			const cap = await check_symlink_capability()
			symlinkCardsModal.value?.show({
				instanceNames: [single.name],
				symlinkCapable: cap,
			})
			return
		}

		// Multiple instances → show selection modal
		dropDebug(
			'handleDropConfirm: multiple instances from .minecraft, showing launcher import modal',
		)
		launcherImportModal.value?.show(results)
		return
	}

	// Compressed launcher folders (a zipped `.minecraft`, single instance
	// folder or launcher directory) are extracted once to a temp dir; the
	// scan and the import both operate on that extraction so the archive is
	// unpacked a single time. The temp dir is removed on every terminal path
	// (success, failure, or cancel).
	if (isLauncherImport && type === 'instance') {
		const launcherType =
			classification!.item_type === 'hmcl_launcher' ? 'HMCL' : classification!.launcher_type!
		const basePath =
			classification!.item_type === 'hmcl_launcher'
				? classification!.launcher_dir!
				: classification!.base_path!
		dropDebug('handleDropConfirm: launcher import branch', { launcherType, basePath })

		let scanBasePath = basePath
		if (isZipPath(basePath)) {
			scanningInstances.value = true
			try {
				const tempDir = await extractZipToTemp(basePath)
				launcherZipTempDir.value = tempDir
				scanBasePath = classification!.innerBase
					? `${tempDir}/${classification!.innerBase}`
					: tempDir
				dropDebug('handleDropConfirm: extracted launcher zip', {
					tempDir,
					innerBase: classification!.innerBase,
					scanBasePath,
				})
			} catch (error) {
				launcherZipTempDir.value = null
				const errorDetail = error instanceof Error ? error.message : String(error)
				console.error('[DropFlow] launcher zip extraction failed:', errorDetail, basePath)
				dropDebug('handleDropConfirm: launcher zip extraction failed', error)
				addNotification({
					title: formatMessage(messages.dropExtractFailed),
					text: errorDetail,
					type: 'error',
				})
				return
			} finally {
				scanningInstances.value = false
			}
		}

		currentImportContext.value = { launcherType, basePath: scanBasePath }
		scanningInstances.value = true
		let results: ScanResult[]
		try {
			results = await scanLauncherInstances(launcherType, scanBasePath)
		} catch (error) {
			currentImportContext.value = null
			dropDebug('handleDropConfirm: launcher scan failed', error)
			addNotification({ title: formatMessage(messages.dropScanFailed), type: 'error' })
			cleanupLauncherZipTemp()
			return
		} finally {
			scanningInstances.value = false
		}
		const totalInstances = results.reduce((s, r) => s + r.instances.length, 0)
		dropDebug('handleDropConfirm: launcher scan result', { totalInstances, results })

		if (totalInstances === 0) {
			currentImportContext.value = null
			dropDebug('handleDropConfirm: no instances found')
			addNotification({ title: formatMessage(messages.dropNoInstances), type: 'warning' })
			cleanupLauncherZipTemp()
			return
		}

		if (totalInstances === 1 && results[0]?.instances[0]) {
			// Single instance → go directly to symlink method selection
			const single = results[0].instances[0]
			dropDebug('handleDropConfirm: single instance, showing symlink modal', {
				name: single.name,
				path: single.path,
			})
			selectedInstances.value = [
				{ launcherType, basePath: scanBasePath, name: single.name, path: single.path },
			]
			if (launcherZipTempDir.value) {
				// Compressed sources live in a temporary extraction that is
				// deleted after the import, so symlink is never an option.
				dropDebug('handleDropConfirm: zip source, importing as copy')
				await onSymlinkMethodConfirmed(false)
				return
			}
			const cap = await check_symlink_capability()
			symlinkCardsModal.value?.show({
				instanceNames: [single.name],
				symlinkCapable: cap,
			})
			return
		}

		// Multiple instances → show selection modal
		dropDebug('handleDropConfirm: multiple instances, showing launcher import modal')
		launcherImportModal.value?.show(results)
		return
	}

	if (type === 'modpack') {
		dropDebug('handleDropConfirm: modpack branch', { filePath, fileName })

		if (!filePath) {
			dropDebug('handleDropConfirm: modpack — no filePath, aborting')
			addNotification({ title: formatMessage(messages.dropModpackInstallFailed), type: 'error' })
			return
		}

		// ── Replace "Processing..." with "Installing..." immediately (pure frontend) ──
		clearDropProcessingNotification()
		await installModpackFromPath(filePath, fileName, { persistUntilDone: true })
		trackEvent('InstanceCreate', { source: 'DropConfirmModpack' })
		await router.push('/library')
		return
	}

	// Content types that can be installed
	const contentTypes = [
		'mod',
		'resource_pack',
		'shader_pack',
		'world_save',
		'litematic',
		'schematic',
	]
	if (!contentTypes.includes(type)) {
		dropDebug('handleDropConfirm: type not in contentTypes — FALLTHROUGH, no handler!', {
			type,
			contentTypes,
		})
		return
	}

	dropDebug('handleDropConfirm: content install branch', {
		type,
		isInInstance: isInInstance.value,
		hasInstanceId: !!instanceId.value,
	})

	if (isInInstance.value && instanceId.value) {
		dropDebug('handleDropConfirm: installing directly to current instance', {
			instanceId: instanceId.value,
		})
		await installContentDirectly(type, filePath, instanceId.value)
	} else {
		// Store pending install info for when an instance is selected
		dropDebug('handleDropConfirm: storing pending install, showing instance selection modal')
		pendingInstall.value = { type, filePath }

		// Load all instances for the selection modal
		let instances: {
			id: string
			name: string
			iconUrl?: string | null
			gameVersion?: string | null
			loader?: string | null
		}[] = []
		try {
			const allInstances = await listInstances()
			instances = allInstances.map((inst) => ({
				id: inst.id,
				name: inst.name,
				iconUrl: inst.icon_path ? convertFileSrc(inst.icon_path) : null,
				gameVersion: inst.game_version || null,
				loader: inst.loader || null,
			}))
		} catch {
			// If listing fails, show empty list
		}
		genericInstallModal.value?.show({
			contentType: type,
			fileName,
			instances,
		})
	}
}

async function installContentDirectly(type: string, filePath: string, instId: string) {
	try {
		if (type === 'world_save') {
			await import_world_save(instId, filePath)
			addNotification({
				title: formatMessage(messages.dropWorldImportedTitle),
				text: formatMessage(messages.dropWorldImportedText),
				type: 'success',
			})
			return
		}

		if (type === 'mod') {
			let meta: {
				minecraft_version?: string
				loader?: string
				name?: string
				mod_id?: string
			} | null = null
			let modrinthLookup: ModrinthLookupResult | null = null

			const metaStr = await extractModMetadata(filePath)
			dropDebug('installContentDirectly: mod metadata extraction', { filePath, hasMeta: !!metaStr })

			if (metaStr) {
				try {
					meta = JSON.parse(metaStr)
					dropDebug('installContentDirectly: parsed mod metadata', { meta })
				} catch (e) {
					dropDebug('installContentDirectly: failed to parse mod metadata', { error: e })
				}
			}

			try {
				modrinthLookup = await lookupModHash(filePath)
				dropDebug('installContentDirectly: modrinth hash lookup', { found: !!modrinthLookup })
			} catch (e) {
				dropDebug('installContentDirectly: hash lookup failed', { error: e })
			}

			const inst = await getInstance(instId)
			dropDebug('installContentDirectly: instance details', {
				inst: inst?.id,
				game_version: inst?.game_version,
				loader: inst?.loader,
			})

			if (inst && meta?.minecraft_version) {
				const instVersion = inst.game_version
				const instLoader = inst.loader
				const modMcVersion = meta.minecraft_version
				const modLoader = meta.loader

				let versionMismatch = false
				if (modMcVersion && instVersion) {
					versionMismatch = !isVersionInRange(instVersion, modMcVersion)
				}

				let loaderMismatch = false
				if (modLoader && instLoader) {
					loaderMismatch = !areLoadersCompatible(modLoader, instLoader)
				}

				dropDebug('installContentDirectly: compatibility check', {
					versionMismatch,
					loaderMismatch,
					modMcVersion,
					instVersion,
					modLoader,
					instLoader,
				})

				if (versionMismatch || loaderMismatch) {
					pendingDropIncompatibility.value = {
						filePath,
						instId,
						type,
						instVersion,
						instLoader,
						meta,
						modrinthLookup,
					}
					const warning = formatMessage(messages.dropInstallModWarning, {
						modVersion: modMcVersion ?? 'any',
						modLoader: modLoader ?? 'any',
						instVersion: instVersion ?? 'any',
						instLoader: instLoader ?? 'none',
					})
					contentInstallIncompatibilityWarningVersions.value = []
					contentInstallIncompatibilityWarningCurrentGameVersion.value = instVersion ?? ''
					contentInstallIncompatibilityWarningCurrentLoader.value = instLoader ?? ''
					contentInstallIncompatibilityWarningProjectType.value = 'mod'
					contentInstallIncompatibilityWarningProjectName.value = meta?.name ?? 'Mod'
					contentInstallIncompatibilityWarningMessage.value = warning
					contentInstallIncompatibilityWarningInstalling.value = false
					await nextTick()
					incompatibilityWarningModal.value?.show()
					return
				}
			} else {
				dropDebug('installContentDirectly: skipping version check', {
					hasInstance: !!inst,
					hasModVersion: !!meta?.minecraft_version,
				})
			}
		}

		const projectType = contentFileProjectTypeMap[type]
		await add_project_from_path(instId, filePath, projectType)
		addNotification({
			title: formatMessage(messages.dropContentInstalledTitle),
			text: formatMessage(messages.dropContentInstalledText),
			type: 'success',
		})
	} catch (e) {
		let errMsg = e instanceof Error ? e.message : typeof e === 'string' ? e : JSON.stringify(e)
		try {
			const lockInfo = await detectFileLock(filePath)
			if (lockInfo.length > 0) {
				const lockLines = lockInfo.map((p) => `  PID ${p.pid}: ${p.name} (${p.path})`).join('\n')
				errMsg += `\n\nFile locked by:\n${lockLines}`
			}
		} catch {
			// Lock detection is best-effort
		}
		addNotification({
			title: formatMessage(messages.dropInstallFailedTitle),
			text: errMsg,
			type: 'error',
		})
	}
}

/**
 * Show a popup notification asking the user to confirm force-analysis
 * (extraction + classification) of a ZIP archive that couldn't be identified
 * from entry names alone.
 */
function showForceAnalysisPrompt(classification: ClassificationResult) {
	const filePath = dropFilePath.value
	if (!filePath) return

	dropDebug('showForceAnalysisPrompt: showing force-analysis prompt', {
		reason: classification.reason,
		filePath,
	})

	addPopupNotification({
		title: formatMessage(messages.dropUnknownForceAnalysisTitle),
		text: formatMessage(messages.dropUnknownForceAnalysisText),
		type: 'info',
		autoCloseMs: null,
		buttons: [
			{
				label: formatMessage(messages.dropUnknownForceAnalysisButton),
				action: async () => {
					const analyzingNotification = addNotification({
						title: formatMessage(messages.dropUnknownForceAnalyzing),
						type: 'info',
						autoCloseMs: null,
					})

					try {
						const result = await classifyDroppedItemWithExtraction(filePath)
						notificationManager.removeNotification(analyzingNotification.id)

						if (result.item_type === 'unknown') {
							addNotification({
								title: formatMessage(messages.dropUnknownForceAnalysisFailedTitle),
								text: formatMessage(messages.dropUnknownForceAnalysisFailedText),
								type: 'error',
							})
							return
						}

						// Success — the user already confirmed the unpack.
						await continueWithClassification(result, filePath)
					} catch (e) {
						notificationManager.removeNotification(analyzingNotification.id)
						addNotification({
							title: formatMessage(messages.dropUnknownForceAnalysisFailedTitle),
							text: e instanceof Error ? e.message : String(e),
							type: 'error',
						})
					}
				},
				color: 'brand',
			},
		],
	})
}

/**
 * Route an already-confirmed classification result through the same confirm
 * flow used by a normal drop. Unknown results surface an error notification.
 */
async function continueWithClassification(result: ClassificationResult, fallbackFileName: string) {
	if (result.item_type === 'unknown') {
		addNotification({
			title: formatMessage(messages.dropUnknownTitle),
			text: unknownReasonMessage(result.reason),
			type: 'error',
		})
		return
	}
	dropClassification.value = result
	dropFilePath.value = result.file_path ?? result.base_path ?? ''
	dropFileName.value =
		result.file_path?.split(/[/\\]/).pop() ??
		result.base_path?.split(/[/\\]/).pop() ??
		fallbackFileName

	switch (result.item_type) {
		case 'modpack':
			await handleDropConfirm('modpack')
			break
		case 'world_save':
			await handleDropConfirm('world_save')
			break
		case 'launcher':
		case 'hmcl_launcher':
			await handleDropConfirm('instance')
			break
		default:
			// mod, resource_pack, shader_pack, litematic to content install
			await handleDropConfirm(result.item_type)
			break
	}
}

/**
 * Show a popup asking the user to confirm unpacking nested archives before
 * the classifier stages them, reporting their total size. On confirmation
 * the archive is re-classified with nested unpacking allowed.
 */
function showNestedUnpackPrompt(classification: ClassificationResult) {
	const filePath = dropFilePath.value
	if (!filePath) return

	dropDebug('showNestedUnpackPrompt: nested archives need unpacking', {
		reason: classification.reason,
		filePath,
	})

	const sizeBytes = Number(classification.reason?.match(/total (\d+) bytes/i)?.[1] ?? 0)
	const sizeLabel =
		sizeBytes > 0
			? sizeBytes >= 1024 * 1024
				? `${(sizeBytes / (1024 * 1024)).toFixed(1)} MB`
				: `${Math.max(1, Math.round(sizeBytes / 1024))} KB`
			: '?'

	addPopupNotification({
		title: formatMessage(messages.dropNestedUnpackTitle),
		text: formatMessage(messages.dropNestedUnpackText, { size: sizeLabel }),
		type: 'info',
		autoCloseMs: null,
		buttons: [
			{
				label: formatMessage(messages.dropNestedUnpackButton),
				action: async () => {
					try {
						const result = await classifyDroppedItem(filePath, true)
						await continueWithClassification(result, dropFileName.value || 'file')
					} catch (e) {
						addNotification({
							title: formatMessage(messages.dropProcessFailedTitle),
							text: e instanceof Error ? e.message : String(e),
							type: 'error',
						})
					}
				},
			},
		],
	})
}

/**
 * Map a backend Unknown reason to a user-facing message. Depth-limit and
 * encryption failures have dedicated copy; anything else falls back to the
 * raw reason so technical details stay visible.
 */
function unknownReasonMessage(reason: string | undefined): string {
	const normalized = reason?.toLowerCase() ?? ''
	if (normalized.includes('too deep') || normalized.includes('nesting')) {
		return formatMessage(messages.dropUnknownDepthText)
	}
	if (normalized.includes('encrypted')) {
		return formatMessage(messages.dropUnknownEncryptedText)
	}
	return reason ? reason : formatMessage(messages.dropUnknownText)
}

async function handleGenericInstall(instanceId: string) {
	genericInstallModal.value?.hide()
	const pending = pendingInstall.value
	pendingInstall.value = null
	if (!pending) return

	await installContentDirectly(pending.type, pending.filePath, instanceId)
}

async function handleGenericInstallNavigateCreate() {
	genericInstallModal.value?.hide()
	router.push('/create')
}

let symlinkChoiceResolve: ((symlink: boolean) => void) | null = null

function isZipPath(path: string): boolean {
	return /\.zip$/i.test(path)
}

async function cleanupLauncherZipTemp() {
	const tempDir = launcherZipTempDir.value
	if (!tempDir) return
	launcherZipTempDir.value = null
	try {
		await removeTempDir(tempDir)
		dropDebug('handleDropConfirm: launcher zip temp cleaned', { tempDir })
	} catch (error) {
		dropDebug('handleDropConfirm: launcher zip temp cleanup failed', error)
	}
}

function onLauncherImportCancelled() {
	launcherImportModal.value?.hide()
	cleanupLauncherZipTemp()
}

function chooseImportMethod(options: {
	instanceNames: string[]
	symlinkCapable: 'supported' | 'requires_admin' | 'unsupported'
}): Promise<boolean> {
	return new Promise((resolve) => {
		symlinkChoiceResolve = resolve
		symlinkCardsModal.value?.show(options)
	})
}

async function onImportSelected(
	selections: Array<{
		launcherType: string
		launcherName: string
		instances: Array<{ name: string; path: string }>
	}>,
) {
	const allSelected: Array<{ launcherType: string; basePath: string; name: string; path: string }> =
		[]
	for (const sel of selections) {
		for (const inst of sel.instances) {
			allSelected.push({
				launcherType: sel.launcherType,
				basePath: '',
				name: inst.name,
				path: inst.path,
			})
		}
	}
	if (allSelected.length === 0) return
	selectedInstances.value = allSelected

	if (launcherZipTempDir.value) {
		// Compressed sources are temporary extractions; import as copy.
		dropDebug('onImportSelected: zip source, importing as copy', {
			count: allSelected.length,
		})
		await onSymlinkMethodConfirmed(false)
		return
	}

	const cap = await check_symlink_capability()
	symlinkCardsModal.value?.show({
		instanceNames: allSelected.map((i) => i.name),
		symlinkCapable: cap,
	})
}

function onSymlinkMethodCancelled() {
	if (symlinkChoiceResolve) {
		symlinkChoiceResolve(false)
		symlinkChoiceResolve = null
	}
	symlinkCardsModal.value?.hide()
	cleanupLauncherZipTemp()
}

async function onSymlinkMethodConfirmed(symlink: boolean) {
	// Resolve the promise-based chooser first (if called from creation-modal flow)
	if (symlinkChoiceResolve) {
		symlinkChoiceResolve(symlink)
		symlinkChoiceResolve = null
		return
	}

	// Otherwise handle the drop import flow directly
	const instances = selectedInstances.value
	selectedInstances.value = []
	const ctx = currentImportContext.value
	currentImportContext.value = null
	if (instances.length === 0) {
		cleanupLauncherZipTemp()
		return
	}

	// Single instance: simple notification (no progress overlay needed)
	if (instances.length === 1) {
		const inst = instances[0]
		try {
			const job = await import_instance(
				ctx?.launcherType ?? inst.launcherType,
				ctx?.basePath ?? inst.path,
				inst.name,
				symlink,
			)
			await wait_for_install_job(job.job_id)
			addNotification({
				title: formatMessage(messages.dropInstanceImportedTitle),
				text: formatMessage(messages.dropInstanceImportedText, { name: inst.name }),
				type: 'success',
			})
		} catch (e) {
			addNotification({
				title: formatMessage(messages.dropImportFailedTitle),
				text: formatMessage(messages.dropImportFailedText, { name: inst.name, error: String(e) }),
				type: 'error',
			})
		} finally {
			cleanupLauncherZipTemp()
		}
		return
	}

	// Multiple instances: show cumulative progress
	const total = instances.length
	let completed = 0
	let failedCount = 0

	let progressNotif = addNotification({
		title: formatMessage(messages.dropImportProgressTitle),
		text: formatMessage(messages.dropImportProgressText, { current: 0, total }),
		type: 'info',
		autoCloseMs: null,
	})

	for (let i = 0; i < instances.length; i++) {
		const inst = instances[i]

		// Update progress notification
		notificationManager.removeNotification(progressNotif.id)
		progressNotif = addNotification({
			title: formatMessage(messages.dropImportProgressTitle),
			text: formatMessage(messages.dropImportProgressText, {
				current: i + 1,
				total,
			}),
			type: 'info',
			autoCloseMs: null,
		})

		try {
			const job = await import_instance(
				ctx?.launcherType ?? inst.launcherType,
				ctx?.basePath ?? inst.path,
				inst.name,
				symlink,
			)
			await wait_for_install_job(job.job_id)
			completed++
		} catch (e) {
			failedCount++
			addNotification({
				title: formatMessage(messages.dropImportFailedTitle),
				text: formatMessage(messages.dropImportFailedText, { name: inst.name, error: String(e) }),
				type: 'error',
			})
		}
	}

	cleanupLauncherZipTemp()

	// Final summary — replace progress notification
	notificationManager.removeNotification(progressNotif.id)
	if (failedCount === 0) {
		addNotification({
			title: formatMessage(messages.dropImportCompletedTitle),
			text: formatMessage(messages.dropImportCompletedText, { count: total }),
			type: 'success',
		})
	} else {
		addNotification({
			title: formatMessage(messages.dropImportCompletedTitle),
			text: formatMessage(messages.dropImportCompletedPartialText, {
				completed,
				failed: failedCount,
				total,
			}),
			type: 'warning',
		})
	}
}

async function handleDropHelp() {
	await router.push('/help/drop')
	await confirmDropModal.value?.hide()
}

watch(incompatibilityWarningModal, (modal) => {
	if (modal) {
		setContentIncompatibilityWarningModal(modal)
	}
})

setupAuthProvider(credentials, async (_redirectPath) => {
	if (AxolotlBrandConfig.capabilities.privateModrinthServices) await signIn()
})

async function validateSession(sessionToken) {
	try {
		const response = await tauriFetch(`${getOfficialLabrinthBaseUrl()}/v2/user`, {
			method: 'GET',
			headers: { Authorization: sessionToken },
		})
		if (response.status === 401) return false
		return true
	} catch {
		return true
	}
}

async function fetchCredentials() {
	if (!AxolotlBrandConfig.capabilities.privateModrinthServices) {
		credentials.value = null
		return
	}
	const creds = await getCreds().catch(handleError)
	if (creds && creds.user_id) {
		if (creds.session && !(await validateSession(creds.session))) {
			await logout().catch(handleError)
			credentials.value = null
			return
		}
		creds.user = await get_user(creds.user_id, 'bypass').catch(handleError)
	}
	credentials.value = creds ?? null
}

async function signIn() {
	modrinthLoginFlowWaitModal.value.show()

	try {
		await login()
		await fetchCredentials()
	} catch (error) {
		if (
			typeof error === 'object' &&
			typeof error['message'] === 'string' &&
			error.message.includes('Login canceled')
		) {
			// Not really an error due to being a result of user interaction, show nothing
		} else {
			handleError(error)
		}
	} finally {
		modrinthLoginFlowWaitModal.value.hide()
	}
}

async function logOut() {
	await logout().catch(handleError)
	await fetchCredentials()
}

onMounted(() => {
	invoke('show_window')

	error.setErrorModal(errorModal.value)
	error.setMinecraftAuthErrorModal(minecraftAuthErrorModal.value)

	setContentIncompatibilityWarningModal(incompatibilityWarningModal.value)
	setContentInstallModal(modInstallModal.value)
	setContentInstallModpackAlreadyInstalledModal(contentInstallModpackAlreadyInstalledModal.value)
	setContentInstallCurseForgeManualDownloadsModal(
		contentInstallCurseForgeManualDownloadsModal.value,
	)
	setModpackAlreadyInstalledModal(modpackAlreadyInstalledModal.value)
	setServerAddServerToInstanceModal(addServerToInstanceModal.value)
	setServerInstallToPlayModal(installToPlayModal.value)
	setServerUpdateToPlayModal(updateToPlayModal.value)
})

const accounts = ref(null)
provide('accountsCard', accounts)

command_listener(handleCommand)

async function handleCommand(e) {
	if (!e) return
	if (e.event === 'OpenSeedMap') {
		const query = Object.fromEntries(new URLSearchParams(e.query ?? ''))
		await router.push({ path: '/lab/seed-map', query })
		return
	}
	if (offline.value && e.event !== 'LaunchInstance') {
		await router.push('/library')
		return
	}

	if (e.event === 'RunMRPack') {
		// RunMRPack should directly install a local modpack file given a path;
		// non-mrpack archives (CurseForge/MCBBS/HMCL/MultiMC zips) are format-sniffed by the backend
		if (e.path.endsWith('.mrpack') || e.path.endsWith('.zip')) {
			const location = { type: 'fromFile', path: e.path }
			const preview = await install_get_modpack_preview(location).catch(handleError)
			if (preview?.unknownFile) {
				const splitPath = e.path.split(/[\\/]/)
				const fileName = splitPath ? splitPath[splitPath.length - 1] : e.path
				unknownPackWarningModal.value?.show(
					() => install_create_modpack_instance(location).then(() => undefined),
					fileName,
				)
			} else {
				await install_create_modpack_instance(location).catch(handleError)
			}
			trackEvent('InstanceCreate', {
				source: 'CreationModalFileDrop',
			})
		}
	} else if (e.event === 'LaunchInstance') {
		const instance = await getInstance(e.id).catch(() => null)
		const handleLaunchCommandError = async (launchError) => {
			const handled =
				(await minecraftCrashModal.value?.handleLaunchError(launchError, {
					instance_id: e.id,
					instance_name: instance?.name || 'Minecraft',
				})) ?? false
			if (!handled) handleError(launchError)
		}
		if (e.server) {
			await start_join_server(e.id, e.server).catch(handleLaunchCommandError)
		} else if (e.singleplayer_world) {
			await start_join_singleplayer_world(e.id, e.singleplayer_world).catch(
				handleLaunchCommandError,
			)
		} else {
			await run(e.id).catch(handleLaunchCommandError)
		}
	} else if (e.event === 'InstallServer') {
		await router.push(`/project/${e.id}`)
		await playServerProject(e.id).catch(handleError)
	} else if (e.event === 'InstallVersion') {
		const version = await get_version(e.id, 'must_revalidate').catch(handleError)
		if (version) {
			await contentInstall
				.install(version.project_id, version.id, null, 'URLConfirmModal', undefined, undefined, {
					showProjectInfo: true,
				})
				.catch(handleError)
		}
	} else {
		await contentInstall
			.install(e.id, null, null, 'URLConfirmModal', undefined, undefined, { showProjectInfo: true })
			.catch(handleError)
	}
}

const appUpdateDownload = {
	progress: appUpdateState.progress,
	version: ref(),
}
let unlistenUpdateDownload

const {
	metered,
	finishedDownloading,
	downloading,
	restarting,
	availableUpdate,
	updateSize,
	updatesEnabled,
} = appUpdateState
let delayedUpdatePopupTimeout = null

const updatePopupMessages = defineMessages({
	updateAvailable: {
		id: 'app.update-popup.title',
		defaultMessage: 'Update available',
	},
	downloadComplete: {
		id: 'app.update-popup.download-complete',
		defaultMessage: 'Download complete',
	},
	meteredBody: {
		id: 'app.update-popup.body.metered',
		defaultMessage: `Block Engine v{version} is available. Click download to install the official signed update.`,
	},
	downloadedBody: {
		id: 'app.update-popup.body.download-complete',
		defaultMessage: `Block Engine v{version} has finished downloading. Reload to update now, or install it when Block Engine closes.`,
	},
	linuxBody: {
		id: 'app.update-popup.body.linux',
		defaultMessage:
			'Block Engine v{version} is available. Use your package manager to update for the latest features and fixes!',
	},
	reload: {
		id: 'app.update-popup.reload',
		defaultMessage: 'Reload to update',
	},
	download: {
		id: 'app.update-popup.download',
		defaultMessage: 'Download ({size})',
	},
	changelog: {
		id: 'app.update-popup.changelog',
		defaultMessage: 'Changelog',
	},
})

function clearDelayedUpdatePopup() {
	if (delayedUpdatePopupTimeout !== null) {
		clearTimeout(delayedUpdatePopupTimeout)
		delayedUpdatePopupTimeout = null
	}
}

function getCurrentUpdatePromptStage() {
	return finishedDownloading.value ? 'downloaded' : 'available'
}

function scheduleDelayedUpdatePopup() {
	clearDelayedUpdatePopup()

	const version = availableUpdate.value?.version
	if (!version) {
		return
	}

	const nextPopupTime = getNextAppUpdatePopupTime(version, getCurrentUpdatePromptStage())
	if (nextPopupTime === null) {
		return
	}

	const delay = nextPopupTime - Date.now()
	if (delay <= 0) {
		showDelayedUpdatePopup()
		return
	}

	delayedUpdatePopupTimeout = setTimeout(showDelayedUpdatePopup, Math.min(delay, 2_147_483_647))
}

function showDelayedUpdatePopup(force = false) {
	const update = availableUpdate.value
	if (!update) {
		return
	}

	const stage = getCurrentUpdatePromptStage()
	if (!force) {
		const nextPopupTime = getNextAppUpdatePopupTime(update.version, stage)
		if (nextPopupTime === null) {
			return
		}

		if (Date.now() < nextPopupTime) {
			scheduleDelayedUpdatePopup()
			return
		}
	}

	if (!finishedDownloading.value) {
		addPopupNotification({
			title: formatMessage(updatePopupMessages.updateAvailable),
			text: formatMessage(updatePopupMessages.meteredBody, { version: update.version }),
			type: 'info',
			autoCloseMs: null,
			buttons: [
				{
					label: formatMessage(updatePopupMessages.download, {
						size: formatBytes(updateSize.value ?? 0),
					}),
					action: () => downloadAvailableAppUpdate(),
					color: 'brand',
				},
				{
					label: formatMessage(updatePopupMessages.changelog),
					action: () => openAppUpdateChangelog(),
					keepOpen: true,
				},
			],
		})
	} else if (finishedDownloading.value) {
		addPopupNotification({
			title: formatMessage(updatePopupMessages.downloadComplete),
			text: formatMessage(updatePopupMessages.downloadedBody, {
				version: update.version,
			}),
			type: 'success',
			autoCloseMs: null,
			buttons: [
				{
					label: formatMessage(updatePopupMessages.reload),
					action: () => installAvailableAppUpdate(),
					color: 'brand',
				},
				{
					label: formatMessage(updatePopupMessages.changelog),
					action: () => openAppUpdateChangelog(),
					keepOpen: true,
				},
			],
		})
	} else {
		scheduleDelayedUpdatePopup()
		return
	}

	markAppUpdatePopupShown(update.version, stage)
}

let lastUpdateSource = 'cnb'

async function performUpdateCheck() {
	const source = getUpdateSource()
	if (source !== lastUpdateSource) {
		availableUpdate.value = null
		updateSize.value = null
		appUpdateDownload.progress.value = 0
		finishedDownloading.value = false
		downloading.value = false
		lastUpdateSource = source
	}

	const update = await checkAppUpdate(source, source === 'server' ? getCustomUpdateUrl() : null)
	if (!update) {
		console.log('No update available')
		return 'up-to-date'
	}

	const isExistingUpdate = update.version === availableUpdate.value?.version

	if (isExistingUpdate) {
		console.log('Update is already known')
		scheduleDelayedUpdatePopup()
		return 'available'
	}

	appUpdateDownload.progress.value = 0
	finishedDownloading.value = false
	downloading.value = false
	updateSize.value = null
	availableUpdate.value = update

	console.log(`Update ${update.version} is available.`)

	metered.value = await isNetworkMetered()
	const settings = await getSettings()
	const autoDownload = settings.auto_download_updates ?? true
	if (autoDownload && !metered.value) {
		console.log('Starting download of update')
		downloadUpdate(update)
	} else {
		console.log('Update is available; automatic download is disabled or the network is metered.')
		markAppUpdateActionable(update.version)
		showDelayedUpdatePopup(true)
	}

	getUpdateSize(update.rid).then((size) => (updateSize.value = size))
	return 'available'
}

async function manualUpdateCheck() {
	updatesEnabled.value = false
	return 'disabled'
}

async function checkUpdates() {
	if (!(await areUpdatesEnabled())) {
		console.log('Skipping update check as updates are disabled in this build or environment')
		updatesEnabled.value = false

		return
	}

	updatesEnabled.value = true
	if (!offline.value) {
		await performUpdateCheck().catch((error) => {
			console.warn('Failed to check for launcher updates', error)
		})
	}
	setTimeout(
		() => {
			checkUpdates()
		},
		5 /* min */ * 60 /* sec */ * 1000 /* ms */,
	)
}

async function downloadAvailableUpdate() {
	return downloadUpdate(availableUpdate.value)
}

async function downloadUpdate(versionToDownload) {
	if (!versionToDownload) {
		handleError(`Failed to download update: no version available`)
		return
	}

	if (downloading.value || appUpdateDownload.progress.value !== 0) {
		console.error(`Update ${versionToDownload.version} already downloading`)
		return
	}

	console.log(`Downloading update ${versionToDownload.version}`)
	downloading.value = true

	try {
		enqueueUpdateForInstallation(versionToDownload.rid)
			.then(() => {
				downloading.value = false
				finishedDownloading.value = true
				unlistenUpdateDownload?.().then(() => {
					unlistenUpdateDownload = null
				})
				console.log('Finished downloading!')
				markAppUpdateActionable(versionToDownload.version, 'downloaded')
				scheduleDelayedUpdatePopup()
			})
			.catch((e) => {
				downloading.value = false
				appUpdateDownload.progress.value = 0
				handleError(e)
			})
		unlistenUpdateDownload = await subscribeToDownloadProgress(
			appUpdateDownload,
			versionToDownload.version,
		)
	} catch (e) {
		downloading.value = false
		appUpdateDownload.progress.value = 0
		handleError(e)
	}
}

async function installUpdate() {
	restarting.value = true

	try {
		await setRestartAfterPendingUpdate(true)
	} catch (e) {
		restarting.value = false
		handleError(e)
		return
	}
	setTimeout(async () => {
		await handleClose()
	}, 250)
}

setAppUpdateActions({
	check: manualUpdateCheck,
	download: downloadAvailableUpdate,
	install: installUpdate,
	changelog: () => undefined,
})

async function openModrinthProjectLinkInApp(parsed) {
	const { slug, pathSuffix, url } = parsed
	const loadToken = loading.begin()
	try {
		const { id } = await tauriApiClient.labrinth.projects_v2.check(slug)
		const query = mergeUrlQuery(route.query, url)
		await router.push({
			path: `/project/${id}${pathSuffix}`,
			query,
			hash: url.hash || undefined,
		})
	} catch (err) {
		if (err instanceof ModrinthApiError && err.statusCode === 404) {
			openUrl(url.href)
		} else {
			handleError(err)
		}
	} finally {
		loading.end(loadToken)
	}
}

function handleClick(e) {
	let target = e.target
	while (target != null) {
		if (target.matches('a')) {
			if (
				target.href &&
				['http://', 'https://', 'mailto:', 'tel:'].some((v) => target.href.startsWith(v)) &&
				!target.classList.contains('router-link-active') &&
				!target.href.startsWith('http://localhost') &&
				!target.href.startsWith('https://tauri.localhost') &&
				!target.href.startsWith('http://tauri.localhost')
			) {
				const parsed = parseModrinthLink(target.href)
				if (target.target !== '_blank' && parsed) {
					void openModrinthProjectLinkInApp(parsed)
				} else {
					openUrl(target.href)
				}
			}
			e.preventDefault()
			break
		}
		target = target.parentElement
	}
}

function handleAuxClick(e) {
	// disables middle click -> new tab
	if (e.button === 1) {
		e.preventDefault()
		// instead do a left click
		const event = new MouseEvent('click', {
			view: window,
			bubbles: true,
			cancelable: true,
		})
		e.target.dispatchEvent(event)
	}
}

provideAppUpdateDownloadProgress(appUpdateDownload)
</script>

<template>
	<SplashScreen v-if="!stateFailed" ref="splashScreen" data-tauri-drag-region />
	<div id="teleports"></div>
	<div
		v-if="stateInitialized && themeStore.customBackgroundPath && !themeStore.transparentBackground"
		class="launcher-background"
		:style="customBackgroundStyle"
	/>
	<div
		v-if="stateInitialized"
		class="app-grid-layout relative"
		:class="{
			'disable-advanced-rendering': !themeStore.advancedRendering,
			'has-custom-background': themeStore.customBackgroundPath && !themeStore.transparentBackground,
			'has-transparent-background': themeStore.transparentBackground,
			'is-maximized': isMaximized,
		}"
	>
		<Transition name="fade">
			<div
				v-if="restarting"
				data-tauri-drag-region
				class="inset-0 fixed bg-black/80 backdrop-blur z-[200] flex items-center justify-center"
			>
				<span
					data-tauri-drag-region
					class="flex items-center gap-4 text-contrast font-semibold text-xl select-none cursor-default"
				>
					<RefreshCwIcon data-tauri-drag-region class="animate-spin w-6 h-6" />
					{{ formatMessage(messages.restarting) }}
				</span>
			</div>
		</Transition>
		<Suspense>
			<AppSettingsModal ref="settingsModal" />
		</Suspense>
		<Suspense>
			<AuthGrantFlowWaitModal ref="modrinthLoginFlowWaitModal" @flow-cancel="cancelLogin" />
		</Suspense>
		<InstanceIconPickerModal ref="instanceIconPickerModal" />
		<CreationFlowModal
			ref="installationModal"
			type="instance"
			show-snapshot-toggle
			:fetch-existing-instance-names="fetchExistingInstanceNames"
			:search-modpacks="searchModpacks"
			:get-project-versions="getProjectVersions"
			:get-loader-manifest="getLoaderManifest"
			:on-import-file-received="onImportFileReceived"
			@create="handleCreate"
			@browse-modpacks="handleBrowseModpacks"
		/>
		<UnknownPackWarningModal ref="unknownPackWarningModal" />
		<div class="block-engine-dock-sensor" aria-hidden="true" @pointerenter="revealNavigationDock" />
		<div
			class="app-grid-navbar block-engine-dock"
			:class="{ 'is-visible': navigationDockVisible }"
			data-tauri-drag-region-exclude
			@pointerenter="revealNavigationDock"
			@pointerleave="scheduleNavigationDockHide"
			@focusin="revealNavigationDock"
			@focusout="scheduleNavigationDockHide"
		>
			<div class="block-nav-section-label">PLAY</div>
			<NavRail>
				<NavButton
					v-tooltip.top="formatMessage(messages.home)"
					to="/"
					:label="formatMessage(messages.home)"
				>
					<HomeIcon />
				</NavButton>
				<NavButton
					v-tooltip.right="formatMessage(messages.discoverContent)"
					data-onboarding-id="nav-discover"
					to="/browse/modpack"
					label="资源星图"
					:disabled="offline"
					:is-primary="() => route.path.startsWith('/browse') && !route.query.i"
					:is-subpage="
						(currentRoute) => currentRoute.path.startsWith('/project') && !currentRoute.query.i
					"
				>
					<CompassIcon />
				</NavButton>
				<NavButton
					v-if="themeStore.featureFlags.worlds_tab"
					v-tooltip.right="formatMessage(messages.worlds)"
					to="/worlds"
					label="世界存档"
				>
					<WorldIcon />
				</NavButton>
				<NavButton
					v-tooltip.right="formatMessage(messages.multiplayer)"
					to="/multiplayer"
					:label="formatMessage(messages.multiplayer)"
					:is-primary="(r) => r.path === '/multiplayer'"
				>
					<UsersIcon />
				</NavButton>
				<NavButton
					v-tooltip.right="formatMessage(messages.library)"
					data-onboarding-id="nav-library"
					to="/library"
					label="游戏环境库"
					:is-primary="(r) => r.path === '/library' || r.path === '/library'"
					:is-subpage="
						() =>
							route.path.startsWith('/instance') ||
							((route.path.startsWith('/browse') || route.path.startsWith('/project')) &&
								route.query.i)
					"
				>
					<LibraryIcon />
				</NavButton>
				<NavButton
					v-tooltip.right="formatMessage(messages.skinSelector)"
					to="/skins"
					label="角色外观"
				>
					<ChangeSkinIcon />
				</NavButton>
				<NavButton
					v-tooltip.right="formatMessage(messages.lab)"
					to="/lab"
					label="实验工坊"
					:is-primary="(currentRoute) => currentRoute.path.startsWith('/lab')"
				>
					<FlaskConicalIcon />
				</NavButton>
				<NavButton
					v-if="!themeStore.autoHideDownloadsButton || downloadManager.activeCount.value > 0"
					v-tooltip.right="formatMessage(messages.downloads)"
					to="/downloads"
					label="任务下载"
					class="relative"
				>
					<DownloadIcon />
					<span v-if="downloadManager.activeCount.value > 0" class="block-nav-count">
						{{ Math.min(downloadManager.activeCount.value, 99) }}
					</span>
				</NavButton>
			</NavRail>
			<NavButton
				v-tooltip.right="formatMessage(messages.createInstance)"
				data-onboarding-id="create-instance"
				to="/create"
				label="新建游戏环境"
				:disabled="offline"
			>
				<PlusIcon />
			</NavButton>
			<NavButton
				v-tooltip.right="formatMessage(commonMessages.settingsLabel)"
				data-onboarding-id="nav-settings"
				:to="() => settingsModal?.show()"
				:label="formatMessage(commonMessages.settingsLabel)"
				class="block-nav-settings"
			>
				<SettingsIcon />
			</NavButton>
			<OverflowMenu
				v-if="AxolotlBrandConfig.capabilities.privateModrinthServices && credentials?.user"
				v-tooltip.top="`Modrinth account`"
				data-onboarding-id="account-entry"
				class="w-12 h-12 text-primary rounded-full flex items-center justify-center text-2xl transition-all bg-transparent hover:bg-button-bg hover:text-contrast border-0 cursor-pointer"
				:options="[
					{
						id: 'view-profile',
						action: () => openUrl('https://modrinth.com/user/' + credentials.user.username),
					},
					{
						id: 'sign-out',
						action: () => logOut(),
						color: 'danger',
					},
				]"
				placement="right-end"
			>
				<Avatar :src="credentials?.user?.avatar_url" alt="" size="32px" circle />
				<template #view-profile>
					<UserIcon />
					<span class="inline-flex items-center gap-1">
						{{ formatMessage(messages.signedInAs) }}
						<span class="inline-flex items-center gap-1 text-contrast font-semibold">
							<Avatar :src="credentials?.user?.avatar_url" alt="" size="20px" circle />
							{{ credentials?.user?.username }}
						</span>
					</span>
					<ExternalIcon />
				</template>
				<template #sign-out> <LogOutIcon /> Sign out </template>
			</OverflowMenu>
			<NavButton
				v-else-if="AxolotlBrandConfig.capabilities.privateModrinthServices"
				v-tooltip.top="'Sign in to a Modrinth account'"
				data-onboarding-id="account-entry"
				:to="() => signIn()"
			>
				<LogInIcon class="text-brand" />
			</NavButton>
		</div>
		<div data-tauri-drag-region class="app-grid-statusbar block-engine-titlebar">
			<div data-tauri-drag-region class="flex min-w-0 flex-1 overflow-hidden p-3">
				<BlockEngineLogo class="h-full w-auto shrink-0 pointer-events-none" />
				<div data-tauri-drag-region class="flex shrink-0 items-center gap-1 ml-3">
					<button
						class="cursor-pointer p-0 m-0 text-contrast border-none outline-none bg-button-bg rounded-full flex items-center justify-center w-6 h-6 hover:brightness-75 transition-all"
						@click="router.back()"
					>
						<LeftArrowIcon />
					</button>
					<button
						class="cursor-pointer p-0 m-0 text-contrast border-none outline-none bg-button-bg rounded-full flex items-center justify-center w-6 h-6 hover:brightness-75 transition-all"
						@click="router.forward()"
					>
						<RightArrowIcon />
					</button>
				</div>
				<Breadcrumbs class="pt-[2px]" />
			</div>
			<section data-tauri-drag-region class="flex shrink-0 ml-auto items-center">
				<ButtonStyled
					v-if="!forceSidebar && themeStore.toggleSidebar"
					:type="sidebarToggled ? 'standard' : 'transparent'"
					circular
				>
					<button
						class="mr-3 transition-transform"
						:class="{ 'rotate-180': !sidebarToggled }"
						@click="sidebarToggled = !sidebarToggled"
					>
						<RightArrowIcon />
					</button>
				</ButtonStyled>
				<div class="flex mr-3">
					<Suspense>
						<AppActionBar />
					</Suspense>
				</div>
				<WindowControls />
			</section>
		</div>
	</div>
	<div
		v-if="stateInitialized"
		class="app-contents"
		:class="{
			'sidebar-enabled': sidebarVisible,
			'disable-advanced-rendering': !themeStore.advancedRendering,
			'has-custom-background': themeStore.customBackgroundPath && !themeStore.transparentBackground,
			'has-transparent-background': themeStore.transparentBackground,
		}"
	>
		<div class="app-viewport flex-grow router-view">
			<div
				class="loading-indicator-container h-8 fixed z-50 pointer-events-none"
				:style="{
					top: 'calc(var(--top-bar-height))',
					left: 'calc(var(--left-bar-width))',
					width: 'calc(100% - var(--left-bar-width) - var(--right-bar-width))',
				}"
			>
				<LoadingBar position="absolute" />
			</div>
			<div
				v-if="themeStore.featureFlags.page_path"
				class="absolute bottom-0 left-0 m-2 bg-tooltip-bg text-tooltip-text font-semibold rounded-full px-2 py-1 text-xs z-50"
			>
				{{ route.fullPath }}
			</div>
			<div
				id="background-teleport-target"
				class="absolute h-full -z-10 rounded-tl-[--radius-xl] overflow-hidden"
				:style="{
					width: 'calc(100% - var(--right-bar-width))',
				}"
			></div>
			<Admonition
				v-if="authUnreachable"
				type="warning"
				:header="formatMessage(messages.authUnreachableHeader)"
				class="m-6 mb-0"
			>
				{{ formatMessage(messages.authUnreachableBody) }}
			</Admonition>
			<RouterView v-slot="{ Component }">
				<template v-if="Component">
					<Suspense @pending="onSuspensePending" @resolve="onSuspenseResolve">
						<component :is="Component"></component>
					</Suspense>
				</template>
			</RouterView>
		</div>
		<div
			class="app-sidebar mt-px shrink-0 flex flex-col border-0 border-l-[1px] border-[--brand-gradient-border] border-solid"
		>
			<div
				v-overlay-scrollbars="sidebarOverlayScrollbarsOptions"
				class="app-sidebar-scrollable relative min-h-0 flex-1"
				data-overlayscrollbars-initialize
			>
				<div id="sidebar-teleport-target" class="sidebar-teleport-content"></div>
				<div class="sidebar-default-content" :class="{ 'sidebar-enabled': sidebarVisible }">
					<div class="p-4 border-0 border-b-[1px] border-[--brand-gradient-border] border-solid">
						<h3 class="text-base text-primary font-medium m-0">
							{{ formatMessage(messages.playingAs) }}
						</h3>
						<suspense>
							<AccountsCard ref="accounts" />
						</suspense>
					</div>
					<div id="sidebar-default-teleport-target"></div>
				</div>
			</div>
		</div>
	</div>
	<I18nDebugPanel />
	<NotificationPanel
		:has-sidebar="sidebarVisible"
		:on-error-action="exportNotificationErrorLogs"
		:error-action-label="formatMessage(messages.exportErrorLogs)"
	/>
	<PopupNotificationPanel
		:has-sidebar="sidebarVisible"
		:on-error-action="exportNotificationErrorLogs"
		:error-action-label="formatMessage(messages.exportErrorLogs)"
	/>
	<MinecraftCrashModal ref="minecraftCrashModal" @error="handleError" />
	<JavaDownloadConfirmationModal ref="javaDownloadConfirmationModal" />
	<CommunityAnnouncementModal ref="communityAnnouncementModal" />
	<UpdateAnnouncementModal ref="updateAnnouncementModal" @closed="handleUpdateAnnouncementClosed" />
	<ErrorModal ref="errorModal" />
	<MinecraftAuthErrorModal ref="minecraftAuthErrorModal" />
	<ContentInstallModal
		ref="modInstallModal"
		:instances="contentInstallInstances"
		:compatible-loaders="contentInstallLoaders"
		:game-versions="contentInstallGameVersions"
		:loading="contentInstallLoading"
		:default-tab="contentInstallDefaultTab"
		:preferred-loader="contentInstallPreferredLoader"
		:preferred-game-version="contentInstallPreferredGameVersion"
		:release-game-versions="contentInstallReleaseGameVersions"
		:project-info="contentInstallProjectInfo"
		:symlink-target="contentInstallSymlinkTarget"
		@install="handleInstallToInstance"
		@create-and-install="handleCreateAndInstall"
		@navigate="handleContentInstallNavigate"
		@cancel="handleContentInstallCancel"
	/>
	<ModpackAlreadyInstalledModal
		ref="modpackAlreadyInstalledModal"
		@create-anyway="handleModpackDuplicateCreateAnyway"
		@go-to-instance="handleModpackDuplicateGoToInstance"
	/>
	<AddServerToInstanceModal
		ref="addServerToInstanceModal"
		:symlink-target="addServerSymlinkTarget"
	/>
	<ContentUpdaterModal
		ref="incompatibilityWarningModal"
		mode="incompatibility-warning"
		:versions="contentInstallIncompatibilityWarningVersions"
		:current-game-version="contentInstallIncompatibilityWarningCurrentGameVersion"
		:current-loader="contentInstallIncompatibilityWarningCurrentLoader"
		current-version-id=""
		:is-app="true"
		:project-type="contentInstallIncompatibilityWarningProjectType"
		:project-icon-url="contentInstallIncompatibilityWarningProjectIconUrl"
		:project-name="contentInstallIncompatibilityWarningProjectName"
		:warning="contentInstallIncompatibilityWarningMessage"
		:action-loading="contentInstallIncompatibilityWarningInstalling"
		@update="handleIncompatibilityWarningUpdate"
		@cancel="handleIncompatibilityWarningCancel"
		@search-compat="handleDropInstallSearchCompat"
	/>
	<ModpackAlreadyInstalledModal
		ref="contentInstallModpackAlreadyInstalledModal"
		@create-anyway="handleContentInstallModpackDuplicateCreateAnyway"
		@go-to-instance="handleContentInstallModpackDuplicateGoToInstance"
	/>
	<CurseForgeManualDownloadsModal
		ref="contentInstallCurseForgeManualDownloadsModal"
		@view-instance="handleContentInstallModpackDuplicateGoToInstance"
		@imported="handleContentInstallCurseForgeManualDownloadsImported"
	/>
	<InstallToPlayModal ref="installToPlayModal" />
	<UpdateToPlayModal ref="updateToPlayModal" />

	<!-- Global drop overlay -->
	<div
		v-if="isDragging && !onSkinsPage"
		class="fixed inset-0 z-[9999] bg-black/40 flex items-center justify-center pointer-events-none"
	>
		<div class="rounded-2xl border-2 border-dashed border-brand bg-surface-2/90 p-8 text-center">
			<p class="text-lg text-contrast">{{ formatMessage(messages.dropOverlayTitle) }}</p>
			<p class="text-sm text-secondary mt-2">{{ formatMessage(messages.dropOverlaySubtitle) }}</p>
		</div>
	</div>

	<!-- Processing overlay -->
	<div
		v-if="(isProcessing || scanningInstances) && !isDragging && !onSkinsPage"
		class="fixed inset-0 z-[9999] bg-black/20 flex items-center justify-center"
	>
		<div class="flex flex-col items-center gap-3">
			<SpinnerIcon class="h-10 w-10 animate-spin text-contrast" />
			<span v-if="scanningInstances" class="text-sm text-secondary"
				>{{ formatMessage(messages.dropScanning) }}…</span
			>
		</div>
	</div>

	<!-- Drop type confirmation modal -->
	<ConfirmDropTypeModal
		ref="confirmDropModal"
		:classification="dropClassification"
		:file-name="dropFileName"
		@confirm="handleDropConfirm"
		@cancel="handleDropCancel"
		@help="handleDropHelp"
	/>

	<!-- Generic content install modal (instance selection when not in an instance page) -->
	<GenericContentInstallModal
		ref="genericInstallModal"
		@install="handleGenericInstall"
		@cancel="dropClassification = null"
		@navigate-create="handleGenericInstallNavigateCreate"
	/>

	<!-- Launcher import instance selection modal -->
	<LauncherImportModal
		ref="launcherImportModal"
		@confirm="onImportSelected"
		@cancel="onLauncherImportCancelled"
	/>

	<!-- Symlink method selection modal -->
	<SymlinkMethodCards
		ref="symlinkCardsModal"
		@confirm="onSymlinkMethodConfirmed"
		@cancel="onSymlinkMethodCancelled"
	/>

	<OnboardingOverlay
		:visible="showOnboarding"
		:mode="onboardingMode"
		@complete="finishOnboarding"
		@skip="skipOnboarding"
		@request-close-settings="closeOnboardingSettings"
	/>
</template>

<style lang="scss" scoped>
.app-grid-layout,
.app-contents {
	--top-bar-height: 4.25rem;
	--left-bar-width: 0px;
	--right-bar-width: 320px;
	--be-water: #3f9972;
	--be-water-deep: #24644a;
	--be-grass: #62b96b;
	--be-stone: #263640;
	--be-sky: #dff4ff;
}

.app-grid-layout {
	display: block;
	position: relative;
	background:
		radial-gradient(
			circle at 18% 8%,
			color-mix(in srgb, var(--color-brand) 20%, transparent),
			transparent 34rem
		),
		radial-gradient(
			circle at 82% 92%,
			color-mix(in srgb, var(--color-brand) 13%, transparent),
			transparent 30rem
		),
		var(--color-bg);
	height: 100vh;
}

.quick-instance-scroll {
	-ms-overflow-style: none;
	scrollbar-width: none;

	&::-webkit-scrollbar {
		display: none;
	}
}

.launcher-background {
	position: fixed;
	inset: -3rem;
	z-index: 0;
	pointer-events: none;
	background-position: center;
	background-size: cover;
	background-repeat: no-repeat;
	transition:
		filter 180ms ease,
		opacity 180ms ease;
}

.app-grid-layout.has-custom-background,
.app-grid-layout.has-transparent-background {
	&:not(.is-maximized) {
		border-radius: 8px;
		clip-path: inset(0 round 8px);
		overflow: hidden;
	}
	background-color: transparent;

	.app-grid-navbar,
	.app-grid-statusbar {
		background-color: color-mix(in srgb, var(--color-raised-bg) 82%, transparent) !important;
		backdrop-filter: blur(18px) saturate(120%);
	}
}

.app-grid-navbar {
	grid-area: nav;
	position: relative;
	z-index: 2;
}

.block-engine-dock-sensor {
	position: fixed;
	left: 50%;
	bottom: 0;
	z-index: 71;
	width: min(35.75rem, calc(100vw - 2rem));
	height: 0.9rem;
	transform: translateX(-50%);
}

.block-engine-dock-sensor::after {
	position: absolute;
	left: 50%;
	bottom: 0.22rem;
	width: 3.5rem;
	height: 3px;
	content: '';
	border-radius: 999px;
	background: color-mix(in srgb, var(--color-brand) 58%, var(--color-divider));
	box-shadow: 0 0 0.7rem color-mix(in srgb, var(--color-brand) 24%, transparent);
	transform: translateX(-50%);
	opacity: 0.48;
	transition:
		width 180ms ease,
		opacity 180ms ease;
}

.block-engine-dock-sensor:hover::after {
	width: 5rem;
	opacity: 0.9;
}
.block-engine-dock {
	display: flex;
	position: fixed;
	left: 50%;
	top: auto;
	bottom: 1rem;
	z-index: 70;
	width: min(35.75rem, calc(100vw - 2rem));
	height: 4.5rem;
	align-items: center;
	justify-content: center;
	flex-direction: row;
	gap: 0.15rem;
	padding: 0.38rem;
	box-sizing: border-box;
	overflow: visible;
	border: 1px solid color-mix(in srgb, var(--color-contrast) 14%, transparent);
	border-radius: 1.2rem;
	background: color-mix(in srgb, var(--color-raised-bg) 78%, transparent);
	box-shadow:
		0 1.1rem 3rem rgb(25 54 63 / 18%),
		inset 0 1px color-mix(in srgb, white 78%, transparent);
	backdrop-filter: blur(24px) saturate(145%);
	opacity: 0;
	pointer-events: none;
	transform: translateX(-50%) translateY(calc(100% + 1.4rem)) scale(0.98);
	transform-origin: center bottom;
	transition:
		opacity 180ms ease,
		transform 240ms cubic-bezier(0.2, 0.82, 0.22, 1);
	will-change: transform, opacity;
	isolation: isolate;
}

.block-engine-dock.is-visible {
	opacity: 1;
	pointer-events: auto;
	transform: translateX(-50%) translateY(0) scale(1);
}

.block-engine-dock::before {
	content: '';
	position: absolute;
	inset: 0;
	z-index: -1;
	border-radius: inherit;
	background:
		linear-gradient(110deg, color-mix(in srgb, white 22%, transparent), transparent 36%),
		linear-gradient(180deg, transparent, color-mix(in srgb, var(--color-brand) 8%, transparent));
	pointer-events: none;
}

.block-engine-dock :deep(.nav-rail) {
	min-width: 0;
	flex: 1 1 auto;
	flex-direction: row;
	justify-content: center;
	gap: 0.08rem;
}

.block-engine-dock :deep(.block-nav-button) {
	width: 3.8rem;
	min-width: 3.8rem;
	height: 3.7rem;
	min-height: 3.7rem;
	align-items: center;
	flex-direction: column;
	justify-content: center;
	gap: 0.24rem;
	padding: 0.3rem 0.2rem;
	border-radius: 0.72rem;
	color: var(--color-secondary);
}

.block-engine-dock :deep(.block-nav-icon) {
	width: 1.22rem;
	height: 1.22rem;
	flex: 0 0 1.22rem;
	padding: 0;
	border-radius: 0;
	background: transparent;
	color: currentColor;
}

.block-engine-dock :deep(.block-nav-label) {
	width: 100%;
	font-size: 0.58rem;
	font-weight: 700;
	line-height: 1.1;
	text-align: center;
}

.block-engine-dock :deep(.block-nav-button:hover) {
	background: color-mix(in srgb, var(--color-brand) 10%, var(--color-button-bg));
	color: var(--color-contrast);
}

.block-engine-dock :deep(.router-link-active),
.block-engine-dock :deep(.subpage-active) {
	background: color-mix(in srgb, var(--color-brand) 16%, var(--color-button-bg));
	color: var(--color-brand);
	box-shadow:
		inset 0 0 0 1px color-mix(in srgb, var(--color-brand) 32%, transparent),
		0 0.35rem 0.9rem color-mix(in srgb, var(--color-brand) 18%, transparent);
}

.block-engine-dock :deep(.nav-rail-slider) {
	border: 1px solid color-mix(in srgb, var(--color-brand) 32%, transparent);
	border-radius: 0.72rem;
	background: color-mix(in srgb, var(--color-brand) 14%, var(--color-raised-bg));
	box-shadow: 0 0.35rem 0.9rem color-mix(in srgb, var(--color-brand) 18%, transparent);
}

.block-nav-section-label {
	display: none;
}

.block-engine-dock :deep(.block-nav-settings) {
	margin-top: 0;
}

.block-engine-command-ribbon {
	display: flex;
	position: fixed;
	left: 0;
	right: 0;
	top: var(--top-bar-height);
	z-index: 70;
	height: var(--command-ribbon-height);
	align-items: center;
	gap: 0.75rem;
	padding: 0.55rem 1rem;
	box-sizing: border-box;
	border-bottom: 1px solid color-mix(in srgb, var(--color-contrast) 13%, transparent);
	background:
		linear-gradient(90deg, color-mix(in srgb, #3f9972 7%, transparent), transparent 36%),
		color-mix(in srgb, var(--color-raised-bg) 92%, transparent);
	box-shadow: 0 0.6rem 1.8rem rgb(18 40 33 / 8%);
	backdrop-filter: blur(22px) saturate(118%);
}

.block-engine-command-ribbon::after {
	content: '';
	position: absolute;
	left: 0;
	right: 0;
	bottom: 0;
	height: 2px;
	background: linear-gradient(90deg, #3f9972 0 18%, #b78a45 18% 31%, transparent 31%);
	opacity: 0.72;
}

.block-command-identity {
	display: flex;
	min-width: 10.2rem;
	align-items: center;
	gap: 0.65rem;
	padding-right: 0.9rem;
	border-right: 1px solid color-mix(in srgb, var(--color-contrast) 13%, transparent);
}

.block-command-identity b,
.block-command-identity small {
	display: block;
}

.block-command-identity b {
	font-size: 0.76rem;
	letter-spacing: 0.08em;
}

.block-command-identity small {
	margin-top: 0.14rem;
	color: var(--color-secondary);
	font-size: 0.54rem;
	font-weight: 700;
	letter-spacing: 0.12em;
}

.block-command-pulse {
	width: 1.55rem;
	height: 1.55rem;
	border: 1px solid rgb(63 153 114 / 45%);
	background:
		linear-gradient(90deg, transparent 45%, rgb(255 255 255 / 25%) 45% 55%, transparent 55%),
		linear-gradient(0deg, transparent 45%, rgb(255 255 255 / 25%) 45% 55%, transparent 55%), #3f9972;
	box-shadow: inset 0 0 0 4px rgb(0 0 0 / 8%);
	transform: rotate(45deg);
}

.block-engine-command-ribbon :deep(.nav-rail) {
	display: flex;
	min-width: 0;
	align-items: stretch;
	gap: 0.18rem;
}

.block-engine-command-ribbon :deep(.block-nav-button) {
	width: auto;
	min-width: 4.45rem;
	height: 3.45rem;
	min-height: 3.45rem;
	flex-direction: column;
	justify-content: center;
	gap: 0.22rem;
	padding: 0.35rem 0.55rem;
	border: 1px solid transparent;
	border-radius: 0.35rem;
	color: var(--color-secondary);
}

.block-engine-command-ribbon :deep(.block-nav-icon) {
	width: 1.15rem;
	height: 1.15rem;
	padding: 0;
	background: transparent;
	color: currentColor;
}

.block-engine-command-ribbon :deep(.block-nav-label) {
	width: auto;
	font-size: 0.62rem;
	font-weight: 750;
	white-space: nowrap;
}

.block-engine-command-ribbon :deep(.block-nav-button:hover) {
	border-color: color-mix(in srgb, #3f9972 28%, transparent);
	background: color-mix(in srgb, #3f9972 9%, var(--color-button-bg));
	color: var(--color-contrast);
}

.block-engine-command-ribbon :deep(.router-link-active),
.block-engine-command-ribbon :deep(.subpage-active) {
	border-color: color-mix(in srgb, #3f9972 44%, transparent);
	background: color-mix(in srgb, #3f9972 15%, var(--color-button-bg));
	color: #3f9972;
	box-shadow: inset 0 -3px #3f9972;
}

.block-engine-command-ribbon :deep(.nav-rail-slider) {
	display: none;
}

.block-command-spacer {
	flex: 1;
}

.block-engine-command-ribbon :deep(.block-nav-settings) {
	margin: 0;
}

.block-nav-count {
	margin-left: auto;
	min-width: 1.15rem;
	padding: 0.1rem 0.3rem;
	border-radius: 0.35rem;
	background: #654578;
	color: white;
	font-size: 0.58rem;
	font-weight: 850;
	text-align: center;
}

.dock-toolbox {
	position: relative;
}

.dock-toolbox-trigger {
	display: flex;
	width: 3.65rem;
	height: 3.45rem;
	align-items: center;
	justify-content: center;
	flex-direction: column;
	gap: 0.16rem;
	padding: 0.32rem 0.25rem;
	border: 0;
	border-radius: 0.62rem;
	background: transparent;
	color: var(--color-secondary);
	font: inherit;
	font-size: 0.62rem;
	font-weight: 750;
	cursor: pointer;
}

.dock-toolbox-trigger:hover,
.dock-toolbox-trigger.is-open {
	background: color-mix(in srgb, #3f9972 14%, var(--color-button-bg));
	color: var(--color-contrast);
}

.dock-toolbox-trigger :deep(svg) {
	width: 1.28rem;
	height: 1.28rem;
}

.dock-toolbox-panel {
	position: absolute;
	left: 50%;
	bottom: calc(100% + 0.85rem);
	display: grid;
	width: 17rem;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 0.38rem;
	padding: 0.7rem;
	border: 1px solid color-mix(in srgb, var(--color-contrast) 14%, transparent);
	border-radius: 0.75rem 1.35rem 0.75rem 0.75rem;
	background: color-mix(in srgb, var(--color-raised-bg) 90%, transparent);
	box-shadow: 0 1.2rem 3rem rgb(9 28 38 / 25%);
	backdrop-filter: blur(24px) saturate(135%);
	transform: translateX(-50%);
}

.dock-toolbox-panel::after {
	content: '';
	position: absolute;
	right: 0;
	top: 0;
	width: 1rem;
	height: 1rem;
	background: linear-gradient(225deg, var(--color-bg) 48%, transparent 50%);
}

.dock-toolbox-panel > p {
	grid-column: 1 / -1;
	margin: 0;
	padding: 0.2rem 0.25rem 0.45rem;
	border-bottom: 1px solid color-mix(in srgb, var(--color-contrast) 10%, transparent);
	color: #3f9972;
	font-size: 0.65rem;
	font-weight: 850;
	letter-spacing: 0.12em;
}

.dock-toolbox-panel a {
	display: flex;
	min-width: 0;
	align-items: center;
	gap: 0.55rem;
	padding: 0.65rem;
	border: 1px solid transparent;
	border-radius: 0.55rem;
	color: var(--color-primary);
	font-size: 0.72rem;
	font-weight: 700;
	text-decoration: none;
}

.dock-toolbox-panel a:hover {
	border-color: color-mix(in srgb, #3f9972 25%, transparent);
	background: color-mix(in srgb, #3f9972 10%, var(--color-button-bg));
	color: var(--color-contrast);
}

.dock-toolbox-panel a.disabled {
	pointer-events: none;
	opacity: 0.45;
}

.dock-toolbox-panel a :deep(svg) {
	width: 1.1rem;
	height: 1.1rem;
	color: #3f9972;
}

.dock-toolbox-panel a b {
	margin-left: auto;
	padding: 0.1rem 0.32rem;
	border-radius: 0.35rem;
	background: #654578;
	color: white;
	font-size: 0.58rem;
}

.app-grid-statusbar {
	grid-area: status;
	padding-right: var(--window-controls-width, 0px);
	position: relative;
	z-index: 2;
}

.block-engine-titlebar {
	display: flex;
	height: var(--top-bar-height);
	border-bottom: 1px solid var(--be-chrome-border);
	background: linear-gradient(
		180deg,
		color-mix(in srgb, var(--color-raised-bg) 92%, transparent),
		color-mix(in srgb, var(--color-bg) 78%, transparent)
	);
	box-shadow:
		0 0.35rem 1.4rem var(--be-window-shadow),
		inset 0 -1px rgb(64 132 151 / 10%);
	backdrop-filter: blur(22px) saturate(135%);
}

.block-engine-titlebar button {
	border-radius: 0.45rem !important;
}

[data-tauri-drag-region-exclude] {
	-webkit-app-region: no-drag;
}

.app-contents {
	position: absolute;
	z-index: 1;
	left: var(--left-bar-width);
	top: var(--top-bar-height);
	right: 0;
	bottom: 0;
	height: calc(100vh - var(--top-bar-height));
	background:
		repeating-linear-gradient(
			0deg,
			transparent 0 31px,
			color-mix(in srgb, var(--color-contrast) 3%, transparent) 31px 32px
		),
		repeating-linear-gradient(
			90deg,
			transparent 0 31px,
			color-mix(in srgb, var(--color-contrast) 3%, transparent) 31px 32px
		),
		color-mix(in srgb, var(--color-bg) 92%, var(--be-canvas-tint) 8%);
	border-top-left-radius: 0;
	overflow: hidden;

	display: grid;
	grid-template-columns: 1fr 0px;
	transition: grid-template-columns 220ms cubic-bezier(0.2, 0.8, 0.2, 1);

	&.sidebar-enabled {
		grid-template-columns: 1fr 300px;
	}

	&.has-custom-background,
	&.has-transparent-background {
		background-color: color-mix(in srgb, var(--color-bg) 76%, transparent);
		border-top-left-radius: 0;

		&::before {
			border: none;
			box-shadow: none;
		}

		.loading-indicator-container {
			border-top-left-radius: 0;
		}
	}
}

.app-grid-layout.has-transparent-background {
	.app-grid-navbar,
	.app-grid-statusbar {
		background-color: color-mix(
			in srgb,
			var(--surface-3-opaque) var(--window-alpha-chrome),
			transparent
		) !important;
	}

	// Without native decorations or rounded corners the window edge dissolves
	// into the desktop, so it needs drawing. Dark outside, light inside, to stay
	// legible over any wallpaper.
	&::after {
		content: '';
		position: fixed;
		inset: 0;
		border-radius: inherit;
		z-index: 100;
		pointer-events: none;
		box-shadow:
			inset 0 0 0 1px rgba(0, 0, 0, 0.5),
			inset 0 0 0 2px rgba(255, 255, 255, 0.14);
	}
}

.app-contents.has-transparent-background {
	// Sourced from the opaque snapshot: `--color-bg` is itself translucent in
	// this mode, so mixing it again would compound. Sits slightly below the
	// chosen alpha because pages paint their own surface on top of it.
	background-color: color-mix(
		in srgb,
		var(--surface-1-opaque) calc(var(--window-alpha) * 0.82),
		transparent
	);
}

.loading-indicator-container {
	border-top-left-radius: 0;
	overflow: hidden;
}

.app-sidebar {
	overflow: visible;
	width: 292px;
	position: relative;
	height: calc(100vh - var(--top-bar-height) - 1.7rem);
	margin: 0.85rem 0.85rem 0.85rem 0;
	border: 1px solid color-mix(in srgb, var(--color-contrast) 11%, transparent);
	border-radius: 0.68rem;
	background: var(--be-glass);
	box-shadow: none;
	backdrop-filter: blur(20px) saturate(125%);
}

.disable-advanced-rendering {
	.app-sidebar::before {
		box-shadow: none;
	}

	&.app-contents::before {
		box-shadow: none;
	}

	*,
	:deep(*) {
		box-shadow: none !important;
		--tw-drop-shadow:;
	}
}

.app-sidebar::before {
	content: '';
	box-shadow: none;
	top: 0;
	bottom: 0;
	left: -2rem;
	width: 2rem;
	position: absolute;
	pointer-events: none;
}

.app-viewport {
	flex-grow: 1;
	height: 100%;
	overflow: auto;
	overflow-x: hidden;
	scrollbar-gutter: stable;
}

.app-contents::before {
	z-index: 30;
	content: '';
	position: fixed;
	left: var(--left-bar-width);
	top: var(--top-bar-height);
	right: calc(-1 * var(--left-bar-width));
	bottom: calc(-1 * var(--left-bar-width));
	border-radius: 0;
	box-shadow: none;
	border: 0;
	pointer-events: none;
}

.sidebar-teleport-content {
	display: contents;
}

.sidebar-default-content {
	display: none;
}

.sidebar-teleport-content:empty + .sidebar-default-content.sidebar-enabled {
	display: contents;
}

.popup-survey-enter-active {
	transition:
		opacity 0.25s ease,
		transform 0.25s cubic-bezier(0.51, 1.08, 0.35, 1.15);
	transform-origin: top center;
}

.popup-survey-leave-active {
	transition:
		opacity 0.25s ease,
		transform 0.25s cubic-bezier(0.68, -0.17, 0.23, 0.11);
	transform-origin: top center;
}

.popup-survey-enter-from,
.popup-survey-leave-to {
	opacity: 0;
	transform: translateY(10rem) scale(0.8) scaleY(1.6);
}

@media (prefers-reduced-motion: no-preference) {
	.nav-button-animated-enter-active {
		transition: all 0.5s cubic-bezier(0.15, 1.4, 0.64, 0.96);
	}

	.nav-button-animated-leave-active {
		transition: all 0.25s ease;
	}

	.nav-button-animated-enter-active {
		position: relative;
	}

	.nav-button-animated-enter-active::before {
		content: '';
		inset: 0;
		border-radius: 100vw;
		background-color: var(--color-brand-highlight);
		position: absolute;
		animation: pop 0.5s ease-in forwards;
		opacity: 0;
	}

	@keyframes pop {
		0% {
			scale: 0.5;
		}
		50% {
			opacity: 0.5;
		}
		100% {
			scale: 1.5;
		}
	}

	.nav-button-animated-enter-from {
		scale: 0.5;
		translate: -2rem 0;
		opacity: 0;
	}

	.nav-button-animated-leave-to {
		scale: 0.75;
		opacity: 0;
	}

	.fade-enter-active {
		transition: 0.25s ease-in-out;
	}

	.fade-enter-from {
		opacity: 0;
	}
}
</style>
<style>
.os-theme-dark,
.os-theme-light {
	--os-handle-bg: var(--color-scrollbar) !important;
	--os-handle-bg-hover: var(--color-scrollbar) !important;
	--os-handle-bg-active: var(--color-scrollbar) !important;
}

.mac {
	.app-grid-statusbar {
		padding-left: 5rem;
	}
}

.windows {
	.fake-appbar {
		height: 2.5rem !important;
	}

	.info-card {
		right: 22rem;
	}

	.profile-card {
		right: 8rem;
	}
}
</style>
