<script setup lang="ts">
import { PlusIcon } from '@modrinth/assets'
import {
	ButtonStyled,
	defineMessages,
	injectNotificationManager,
	NavTabs,
	useVIntl,
} from '@modrinth/ui'
import { onUnmounted, shallowRef } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { NewInstanceImage } from '@/assets/icons'
import { useNetworkStatus } from '@/composables/useNetworkStatus'
import { instance_listener } from '@/helpers/events.js'
import { list } from '@/helpers/instance'
import { useBreadcrumbs } from '@/store/breadcrumbs.js'

const { handleError } = injectNotificationManager()
const route = useRoute()
const router = useRouter()
const breadcrumbs = useBreadcrumbs()
const { formatMessage } = useVIntl()

const messages = defineMessages({
	library: { id: 'app.library.title', defaultMessage: '游戏环境库' },
	allInstances: { id: 'app.library.tabs.all-instances', defaultMessage: '全部环境' },
	modpacks: { id: 'app.library.tabs.modpacks', defaultMessage: '整合环境' },
	servers: { id: 'app.library.tabs.servers', defaultMessage: '服务器环境' },
	custom: { id: 'app.library.tabs.custom', defaultMessage: '自定义环境' },
	shared: { id: 'app.library.tabs.shared', defaultMessage: 'Shared with me' },
	saved: { id: 'app.library.tabs.saved', defaultMessage: 'Saved' },
	noInstances: { id: 'app.library.no-instances', defaultMessage: '这里还没有游戏环境' },
	createInstance: {
		id: 'app.library.create-instance',
		defaultMessage: '新建游戏环境',
	},
})

breadcrumbs.setRootContext({ name: formatMessage(messages.library), link: route.path })

const instances = shallowRef(await list().catch(handleError))

const { offline } = useNetworkStatus()

const unlistenInstance = await instance_listener(async () => {
	instances.value = await list().catch(handleError)
})
onUnmounted(() => {
	unlistenInstance()
})
</script>

<template>
	<div data-onboarding-id="library-content" class="environment-library">
		<header class="be-workbench-header library-header">
			<div>
				<p class="be-workbench-kicker">Environment ledger / 世界档案</p>
				<h1 class="be-workbench-title">{{ formatMessage(messages.library) }}</h1>
				<p class="be-workbench-copy">集中查看、筛选和启动你的 Minecraft 配置与世界。</p>
			</div>
			<div class="library-summary">
				<span class="be-data-label">TOTAL</span>
				<strong>{{ instances?.length ?? 0 }}</strong>
				<ButtonStyled color="brand">
					<button
						data-onboarding-id="create-instance"
						:disabled="offline"
						@click="router.push('/create')"
					>
						<PlusIcon />
						{{ formatMessage(messages.createInstance) }}
					</button>
				</ButtonStyled>
			</div>
		</header>

		<div class="library-tabs be-panel">
			<NavTabs
				:links="[
					{ label: formatMessage(messages.allInstances), href: `/library` },
					{ label: formatMessage(messages.modpacks), href: `/library/modpacks` },
					{ label: formatMessage(messages.servers), href: `/library/servers` },
					{ label: formatMessage(messages.custom), href: `/library/custom` },
					{ label: formatMessage(messages.shared), href: `/library/shared`, shown: false },
					{ label: formatMessage(messages.saved), href: `/library/saved`, shown: false },
				]"
			/>
		</div>

		<main class="library-content">
			<template v-if="instances && instances.length > 0">
				<RouterView v-if="route.path.startsWith('/library')" :instances="instances" />
			</template>
			<div v-else class="no-instance be-panel">
				<div class="icon">
					<NewInstanceImage />
				</div>
				<h3>{{ formatMessage(messages.noInstances) }}</h3>
				<p>建立新环境，或导入本地整合包与其他启动器中的游戏。</p>
				<ButtonStyled color="brand">
					<button
						data-onboarding-id="create-instance"
						:disabled="offline"
						@click="router.push('/create')"
					>
						<PlusIcon />
						{{ formatMessage(messages.createInstance) }}
					</button>
				</ButtonStyled>
			</div>
		</main>
	</div>
</template>

<style lang="scss" scoped>
.environment-library {
	display: flex;
	width: min(1180px, 100%);
	min-height: 100%;
	margin: 0 auto;
	padding: clamp(1rem, 2.2vw, 1.8rem);
	box-sizing: border-box;
	flex-direction: column;
	gap: 0.75rem;
}

.library-summary {
	position: relative;
	z-index: 1;
	display: grid;
	grid-template-columns: auto auto;
	align-items: center;
	gap: 0.1rem 0.6rem;
}

.library-summary strong {
	color: var(--color-contrast);
	font-family: var(--be-font-data);
	font-size: 1.65rem;
}

.library-summary :deep(.button-wrapper) {
	grid-column: 1 / -1;
}

.library-tabs {
	padding: 0.4rem;
}

.library-tabs :deep(nav),
.library-tabs :deep(.nav-tabs) {
	gap: 0.25rem;
}

.library-content {
	display: flex;
	min-height: 0;
	flex: 1;
	flex-direction: column;
	gap: 0.75rem;
}

.no-instance {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	min-height: 19rem;
	gap: var(--gap-md);
	padding: 2rem;

	p,
	h3 {
		margin: 0;
	}

	> p {
		max-width: 28rem;
		color: var(--color-secondary);
		font-size: 0.8rem;
		text-align: center;
	}

	.icon {
		svg {
			width: 10rem;
			height: 10rem;
		}
	}
}

@media (max-width: 720px) {
	.library-summary {
		display: none;
	}
}
</style>
