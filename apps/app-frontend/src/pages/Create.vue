<script setup lang="ts">
import { FolderOpenIcon, LeftArrowIcon, SparklesIcon } from '@modrinth/assets'
import { Button, defineMessages, useVIntl } from '@modrinth/ui'
import { inject } from 'vue'
import { useRouter } from 'vue-router'

const { formatMessage } = useVIntl()
const router = useRouter()

const showModal = inject<
	(options?: {
		skipSetupType?: boolean
		initialMode?: 'custom' | 'import'
		onBack?: () => void
	}) => void
>('showCreationModalWithOptions')

const messages = defineMessages({
	title: {
		id: 'create.title',
		defaultMessage: '新建游戏环境',
	},
	subtitle: {
		id: 'create.subtitle',
		defaultMessage: '从零配置一个世界，或接入你已经拥有的游戏内容。',
	},
	newTitle: {
		id: 'create.new.title',
		defaultMessage: '搭建新环境',
	},
	newDescription: {
		id: 'create.new.description',
		defaultMessage: '选择 Minecraft 版本、加载器与内容，建立一套独立运行环境。',
	},
	importTitle: {
		id: 'create.import.title',
		defaultMessage: '接入已有内容',
	},
	importDescription: {
		id: 'create.import.description',
		defaultMessage: '导入其他启动器环境、本地整合包或已有游戏目录。',
	},
	back: {
		id: 'create.back',
		defaultMessage: '返回游戏环境库',
	},
})

const navigateBack = () => router.push('/library')

function handleStartFresh() {
	showModal?.({
		skipSetupType: true,
		initialMode: 'custom',
		onBack: () => router.push('/create'),
	})
}

function handleImportExisting() {
	showModal?.({
		skipSetupType: true,
		initialMode: 'import',
		onBack: () => router.push('/create'),
	})
}
</script>

<template>
	<div class="creation-workbench">
		<div class="creation-shell">
			<header class="be-workbench-header">
				<div>
					<p class="be-workbench-kicker">World assembly / 环境装配台</p>
					<h1 class="be-workbench-title">{{ formatMessage(messages.title) }}</h1>
					<p class="be-workbench-copy">{{ formatMessage(messages.subtitle) }}</p>
				</div>
				<div class="chunk-coordinate" aria-hidden="true"><b>X</b> 00 <b>Z</b> 00</div>
			</header>

			<div data-onboarding-id="creation-methods" class="creation-paths">
				<section class="creation-path creation-path-build">
					<div class="path-index">01</div>
					<div class="path-icon"><SparklesIcon /></div>
					<div class="path-copy">
						<span>BUILD</span>
						<h2>{{ formatMessage(messages.newTitle) }}</h2>
						<p>{{ formatMessage(messages.newDescription) }}</p>
					</div>
					<button data-onboarding-id="creation-method-custom" @click="handleStartFresh">
						开始配置 <span>→</span>
					</button>
				</section>

				<section class="creation-path creation-path-import">
					<div class="path-index">02</div>
					<div class="path-icon"><FolderOpenIcon /></div>
					<div class="path-copy">
						<span>IMPORT</span>
						<h2>{{ formatMessage(messages.importTitle) }}</h2>
						<p>{{ formatMessage(messages.importDescription) }}</p>
					</div>
					<button data-onboarding-id="creation-method-import" @click="handleImportExisting">
						选择来源 <span>→</span>
					</button>
				</section>
			</div>

			<Button transparent class="creation-back" @click="navigateBack">
				<LeftArrowIcon class="size-4" stroke-width="2" />
				{{ formatMessage(messages.back) }}
			</Button>
		</div>
	</div>
</template>

<style scoped>
.creation-workbench {
	width: 100%;
	height: 100%;
	overflow: auto;
	padding: clamp(1rem, 3vw, 2.5rem);
}

.creation-shell {
	display: flex;
	width: min(980px, 100%);
	min-height: 100%;
	margin: 0 auto;
	flex-direction: column;
	justify-content: center;
	gap: 1rem;
}

.chunk-coordinate {
	position: relative;
	z-index: 1;
	padding: 0.65rem 0.8rem;
	border: 1px solid var(--be-seam);
	background: var(--be-panel-muted);
	color: var(--color-secondary);
	font-family: var(--be-font-data);
	font-size: 0.72rem;
}

.chunk-coordinate b {
	color: var(--be-redstone);
}

.creation-paths {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 0.75rem;
}

.creation-path {
	position: relative;
	display: grid;
	min-height: 15.5rem;
	grid-template-columns: auto 1fr;
	grid-template-rows: auto 1fr auto;
	gap: 1rem;
	padding: 1.25rem;
	overflow: hidden;
	border: 1px solid var(--be-seam);
	border-radius: var(--be-radius);
	background: var(--be-panel);
}

.creation-path::after {
	content: '';
	position: absolute;
	right: -2rem;
	bottom: -2rem;
	width: 6rem;
	height: 6rem;
	border: 1rem solid color-mix(in srgb, var(--path-color) 9%, transparent);
	transform: rotate(45deg);
}

.creation-path-build {
	--path-color: var(--be-moss);
}

.creation-path-import {
	--path-color: var(--be-amethyst);
}

.path-index {
	color: var(--path-color);
	font-family: var(--be-font-data);
	font-size: 0.72rem;
	font-weight: 800;
}

.path-icon {
	display: grid;
	width: 2.7rem;
	height: 2.7rem;
	place-items: center;
	border: 1px solid color-mix(in srgb, var(--path-color) 35%, transparent);
	background: color-mix(in srgb, var(--path-color) 13%, transparent);
	color: var(--path-color);
}

.path-icon :deep(svg) {
	width: 1.35rem;
	height: 1.35rem;
}

.path-copy {
	grid-column: 1 / -1;
	align-self: end;
}

.path-copy > span {
	color: var(--path-color);
	font-family: var(--be-font-data);
	font-size: 0.66rem;
	font-weight: 800;
	letter-spacing: 0.12em;
}

.path-copy h2 {
	margin: 0.25rem 0 0;
	color: var(--color-contrast);
	font-size: 1.28rem;
}

.path-copy p {
	margin: 0.45rem 0 0;
	color: var(--color-secondary);
	font-size: 0.78rem;
	line-height: 1.55;
}

.creation-path > button {
	position: relative;
	z-index: 1;
	grid-column: 1 / -1;
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 0.72rem 0.85rem;
	border: 1px solid color-mix(in srgb, var(--path-color) 42%, transparent);
	border-radius: 0.42rem;
	background: color-mix(in srgb, var(--path-color) 12%, var(--be-panel));
	color: var(--color-contrast);
	font: inherit;
	font-size: 0.78rem;
	font-weight: 750;
	cursor: pointer;
}

.creation-path > button:hover {
	background: var(--path-color);
	color: white;
}

.creation-back {
	align-self: flex-start;
}

@media (max-width: 720px) {
	.creation-paths {
		grid-template-columns: 1fr;
	}
}
</style>
