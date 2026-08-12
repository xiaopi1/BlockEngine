<script setup lang="ts">
import { CheckIcon, CopyIcon, ExternalIcon, ShieldCheckIcon, UsersIcon } from '@modrinth/assets'
import { ButtonStyled, Toggle } from '@modrinth/ui'
import { getVersion } from '@tauri-apps/api/app'
import { openUrl } from '@tauri-apps/plugin-opener'
import { ref } from 'vue'

import { AxolotlBrandConfig } from '@/config'
import { get as getSettings, set as setSettings } from '@/helpers/settings'

const currentVersion = await getVersion()
const qqGroupNumber = AxolotlBrandConfig.qqGroupNumber
const qqGroupUri = `mqqapi://card/show_pslcard?src_type=internal&version=1&uin=${encodeURIComponent(qqGroupNumber)}&card_type=group&source=qrcode`

const autoUpdateEnabled = ref(false)
const copied = ref(false)
const groupStatus = ref('')

const settings = await getSettings()
if (settings.auto_download_updates !== false) {
	settings.auto_download_updates = false
	await setSettings(settings)
}

async function copyGroupNumber(announce = true) {
	try {
		await navigator.clipboard.writeText(qqGroupNumber)
		copied.value = true
		if (announce) groupStatus.value = '群号已复制，可直接在 QQ 中搜索并申请加入。'
		setTimeout(() => {
			copied.value = false
		}, 2500)
	} catch (error) {
		console.warn('Failed to copy the official QQ group number', error)
		if (announce) groupStatus.value = `请在 QQ 中搜索群号 ${qqGroupNumber}。`
	}
}

async function joinOfficialGroup() {
	await copyGroupNumber(false)
	try {
		await openUrl(qqGroupUri)
		groupStatus.value = '已尝试打开 QQ 群申请页；群号也已复制。'
	} catch (error) {
		console.warn('Failed to open the QQ group application page', error)
		try {
			await openUrl('https://qun.qq.com/')
		} catch {
			// The copied group number remains a reliable fallback.
		}
		groupStatus.value = `未能直接打开 QQ，请搜索群号 ${qqGroupNumber} 申请加入。`
	}
}
</script>

<template>
	<div class="update-settings">
		<section class="update-hero">
			<div class="update-mark"><UsersIcon /></div>
			<div class="update-copy">
				<small>BLOCK ENGINE RELEASE</small>
				<h2>群内更新</h2>
				<p>当前版本 {{ currentVersion }}。新版安装包统一在方块引擎官方群发布。</p>
			</div>
			<span class="official-badge"><ShieldCheckIcon /> 官方群发布</span>
		</section>

		<section class="update-row">
			<div>
				<strong>自动更新</strong>
				<p>在线自动更新暂时停用，客户端不会在启动时检查、下载或替换自身。</p>
			</div>
			<Toggle id="official-auto-update" v-model="autoUpdateEnabled" disabled />
		</section>

		<section class="group-card">
			<div class="group-card-heading">
				<span class="group-icon"><UsersIcon /></span>
				<div>
					<small>OFFICIAL QQ GROUP</small>
					<h3>官方群更新</h3>
					<p>加入群聊获取新版本、安装说明和重要通知。</p>
				</div>
			</div>

			<div class="group-number" aria-label="官方群号">
				<span>QQ群</span>
				<strong>{{ qqGroupNumber }}</strong>
			</div>

			<div class="group-actions">
				<ButtonStyled color="brand">
					<button type="button" @click="joinOfficialGroup">
						<UsersIcon />
						加入官方群
						<ExternalIcon />
					</button>
				</ButtonStyled>
				<ButtonStyled>
					<button type="button" @click="copyGroupNumber()">
						<CheckIcon v-if="copied" />
						<CopyIcon v-else />
						{{ copied ? '已复制' : '复制群号' }}
					</button>
				</ButtonStyled>
			</div>
			<p v-if="groupStatus" class="group-status" role="status">{{ groupStatus }}</p>
		</section>

		<div class="security-note">
			<ShieldCheckIcon />
			<span>请只从群号 {{ qqGroupNumber }} 对应的官方群获取安装包，谨防第三方冒充。</span>
		</div>
	</div>
</template>

<style scoped>
.update-settings {
	display: flex;
	flex-direction: column;
	gap: 1rem;
}

.update-hero,
.update-row,
.group-card,
.security-note {
	border: 1px solid var(--color-divider);
	border-radius: 0.7rem;
	background: color-mix(in srgb, var(--color-raised-bg) 93%, var(--color-brand) 7%);
}

.update-hero,
.update-row,
.security-note {
	display: flex;
	align-items: center;
	gap: 1rem;
	padding: 1rem;
}

.update-mark,
.group-icon {
	display: grid;
	place-items: center;
	flex: none;
	background: var(--color-brand);
	color: var(--color-accent-contrast);
}

.update-mark {
	width: 2.8rem;
	height: 2.8rem;
	border-radius: 0.65rem;
}

.group-icon {
	width: 3rem;
	height: 3rem;
	border-radius: 0.6rem;
}

.update-mark :deep(svg),
.group-icon :deep(svg),
.official-badge :deep(svg),
.security-note :deep(svg) {
	width: 1.2rem;
	height: 1.2rem;
}

.update-copy,
.update-row > :first-child {
	min-width: 0;
	flex: 1;
}

.update-hero small,
.group-card-heading small {
	color: var(--color-brand);
	font-size: 0.65rem;
	font-weight: 800;
	letter-spacing: 0.14em;
}

.update-hero h2,
.update-hero p,
.update-row p,
.group-card h3,
.group-card p {
	margin: 0;
}

.update-hero h2 {
	margin-top: 0.15rem;
}

.update-hero p,
.update-row p,
.group-card p {
	margin-top: 0.3rem;
	color: var(--color-secondary);
	font-size: 0.82rem;
}

.official-badge {
	display: inline-flex;
	margin-left: auto;
	align-items: center;
	gap: 0.35rem;
	padding: 0.4rem 0.65rem;
	border-radius: 0.45rem;
	background: color-mix(in srgb, var(--color-brand) 14%, transparent);
	color: var(--color-brand);
	font-size: 0.72rem;
	font-weight: 750;
}

.group-card {
	padding: 1.15rem;
	background:
		linear-gradient(
			120deg,
			color-mix(in srgb, var(--color-brand) 12%, transparent),
			transparent 46%
		),
		var(--color-raised-bg);
}

.group-card-heading {
	display: flex;
	align-items: center;
	gap: 0.85rem;
}

.group-card h3 {
	font-size: 1.05rem;
}

.group-number {
	display: flex;
	margin: 1rem 0;
	align-items: center;
	justify-content: space-between;
	padding: 0.85rem 1rem;
	border: 1px dashed color-mix(in srgb, var(--color-brand) 42%, var(--color-divider));
	border-radius: 0.55rem;
	background: color-mix(in srgb, var(--color-bg) 72%, transparent);
}

.group-number span {
	color: var(--color-secondary);
	font-size: 0.78rem;
	font-weight: 700;
}

.group-number strong {
	color: var(--color-contrast);
	font-size: 1.2rem;
	letter-spacing: 0.08em;
}

.group-actions {
	display: flex;
	flex-wrap: wrap;
	gap: 0.65rem;
}

.group-status {
	color: var(--color-brand) !important;
	font-weight: 650;
}

.security-note {
	align-items: flex-start;
	color: var(--color-secondary);
	font-size: 0.8rem;
}

.security-note :deep(svg) {
	flex: none;
	color: var(--color-brand);
}

@media (max-width: 640px) {
	.update-hero {
		align-items: flex-start;
		flex-wrap: wrap;
	}

	.official-badge {
		margin-left: 3.8rem;
	}
}
</style>
