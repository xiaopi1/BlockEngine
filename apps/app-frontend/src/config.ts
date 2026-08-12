const trimTrailingSlash = (url: string) => url.replace(/\/$/, '')

export const BLOCK_ENGINE_UPDATE_MANIFEST_URL =
	import.meta.env.VITE_BLOCK_ENGINE_UPDATE_MANIFEST_URL ||
	'https://san2.top/blockengine/latest.php'

export const AxolotlBrandConfig = Object.freeze({
	productName: '方块引擎',
	shortProductName: '方块引擎',
	organizationName: '方块引擎开源社区',
	shortOrganizationName: 'Block Engine',
	developerName: '方块引擎贡献者',
	website: 'https://github.com/Mystic-Stars/Axolotl',
	sourceUrl: 'https://github.com/Mystic-Stars/Axolotl',
	supportUrl: 'https://github.com/Mystic-Stars/Axolotl/issues',
	qqGroupNumber: '144788610',
	sponsorUrl: 'https://afdian.com/p/9e1d939094b611f1b1c75254001e7c00',
	bundleIdentifier: 'cn.blockengine.launcher',
	deepLinkScheme: 'blockengine',
	userAgent: (version: string, os: string) => `block-engine/launcher/${version} (${os})`,
	capabilities: Object.freeze({
		publicModrinthApi: true,
		privateModrinthServices: false,
		ghsTelemetry: false,
	}),
})

const siteUrl = trimTrailingSlash(import.meta.env.MODRINTH_URL || 'https://modrinth.com')
const officialLabrinthBaseUrl = trimTrailingSlash(
	import.meta.env.MODRINTH_API_BASE_URL || 'https://api.modrinth.com',
)
export const MODRINTH_MIRROR_BASE_URL = 'https://mod.mcimirror.top/modrinth'
type DownloadSourceMode = 'auto' | 'official_only' | 'mirror_preferred' | 'official_preferred'

let modrinthSourceMode: DownloadSourceMode = 'auto'

function autoPrefersMirror() {
	if (typeof navigator === 'undefined') return false

	const languages = [...(navigator.languages ?? []), navigator.language]
	const usesMainlandChinese = languages.some((language) => {
		const normalized = language.toLowerCase().replace('_', '-')
		return normalized.startsWith('zh-cn') || normalized.startsWith('zh-hans')
	})
	const timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone?.toLowerCase()
	const usesMainlandTimeZone = [
		'asia/shanghai',
		'asia/chongqing',
		'asia/harbin',
		'asia/urumqi',
	].includes(timeZone ?? '')

	return usesMainlandTimeZone || (!timeZone && usesMainlandChinese)
}

export function setModrinthSourceMode(sourceMode: DownloadSourceMode) {
	modrinthSourceMode = sourceMode
}

export function setModrinthMirrorEnabled(enabled: boolean) {
	setModrinthSourceMode(enabled ? 'mirror_preferred' : 'official_only')
}

export function getOfficialLabrinthBaseUrl() {
	return officialLabrinthBaseUrl
}

export function getLabrinthBaseUrl() {
	const useMirror =
		modrinthSourceMode === 'mirror_preferred' ||
		(modrinthSourceMode === 'auto' && autoPrefersMirror())
	return useMirror ? MODRINTH_MIRROR_BASE_URL : officialLabrinthBaseUrl
}

export const config = {
	siteUrl,
	labrinthBaseUrl: getLabrinthBaseUrl,
}
