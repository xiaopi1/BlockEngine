<script setup lang="ts">
import { UsersIcon } from '@modrinth/assets'
import { ButtonStyled, commonMessages, defineMessages, NewModal, useVIntl } from '@modrinth/ui'
import { openUrl } from '@tauri-apps/plugin-opener'
import { ref } from 'vue'

import { AxolotlBrandConfig } from '@/config'

const DISMISSAL_KEY = 'block-engine-community-introduction-v1-dismissed'

const { formatMessage } = useVIntl()
const modal = ref<InstanceType<typeof NewModal>>()
const groupStatus = ref('')
const qqGroupUri = `mqqapi://card/show_pslcard?src_type=internal&version=1&uin=${encodeURIComponent(AxolotlBrandConfig.qqGroupNumber)}&card_type=group&source=qrcode`

const messages = defineMessages({
	title: {
		id: 'app.community-announcement.title',
		defaultMessage: 'Welcome to Block Engine',
	},
	response: {
		id: 'app.community-announcement.response',
		defaultMessage:
			'Block Engine brings Minecraft game environments, content, worlds, Java runtimes, and downloads into one workbench.',
	},
	thanks: {
		id: 'app.community-announcement.thanks',
		defaultMessage:
			'This launcher is being improved continuously. Your suggestions and problem reports help shape what comes next.',
	},
	feedbackPrefix: {
		id: 'app.community-announcement.feedback-prefix',
		defaultMessage: 'Official player QQ group: ',
	},
	feedbackSuffix: {
		id: 'app.community-announcement.feedback-suffix',
		defaultMessage:
			'. New releases, installation notes, and important notices are published there.',
	},
	joinGroup: {
		id: 'app.community-announcement.join-group',
		defaultMessage: 'Join official group',
	},
})

function dismiss() {
	localStorage.setItem(DISMISSAL_KEY, 'true')
}

function close() {
	modal.value?.hide()
}

async function joinOfficialGroup() {
	try {
		await navigator.clipboard.writeText(AxolotlBrandConfig.qqGroupNumber)
	} catch {
		// Opening QQ remains useful even when clipboard access is unavailable.
	}

	try {
		await openUrl(qqGroupUri)
		groupStatus.value = `群号 ${AxolotlBrandConfig.qqGroupNumber} 已复制，并已尝试打开 QQ。`
	} catch {
		try {
			await openUrl('https://qun.qq.com/')
		} catch {
			// The visible group number is the final fallback.
		}
		groupStatus.value = `请在 QQ 中搜索群号 ${AxolotlBrandConfig.qqGroupNumber}。`
	}
}

function showIfNeeded() {
	if (localStorage.getItem(DISMISSAL_KEY) !== 'true') {
		modal.value?.show()
	}
}

defineExpose({ showIfNeeded })
</script>

<template>
	<NewModal
		ref="modal"
		:header="formatMessage(messages.title)"
		:on-hide="dismiss"
		max-width="640px"
	>
		<div class="flex flex-col gap-4 text-primary">
			<p class="m-0 leading-relaxed">
				{{ formatMessage(messages.response) }}
			</p>
			<p class="m-0 leading-relaxed">
				{{ formatMessage(messages.thanks) }}
			</p>
			<p class="m-0 leading-relaxed">
				{{ formatMessage(messages.feedbackPrefix)
				}}<span class="font-semibold text-contrast">{{ AxolotlBrandConfig.qqGroupNumber }}</span
				>{{ formatMessage(messages.feedbackSuffix) }}
			</p>
			<p v-if="groupStatus" class="m-0 text-sm text-brand" role="status">
				{{ groupStatus }}
			</p>
		</div>

		<template #actions>
			<div class="flex flex-wrap justify-end gap-2">
				<ButtonStyled type="outlined">
					<button type="button" @click="joinOfficialGroup">
						<UsersIcon />
						{{ formatMessage(messages.joinGroup) }}
					</button>
				</ButtonStyled>
				<ButtonStyled color="brand">
					<button type="button" @click="close">
						{{ formatMessage(commonMessages.closeButton) }}
					</button>
				</ButtonStyled>
			</div>
		</template>
	</NewModal>
</template>
