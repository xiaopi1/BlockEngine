<script lang="ts"></script>

<script setup lang="ts">
import { RightArrowIcon } from '@modrinth/assets'
import { type Component, computed, nextTick, ref } from 'vue'

import { type MessageDescriptor, useVIntl } from '../../composables/i18n'
import { useScrollIndicator } from '../../composables/scroll-indicator'
import NewModal from './NewModal.vue'
export interface Tab {
	name: MessageDescriptor
	icon: Component
	content?: Component
	flushContent?: boolean
	href?: string
	badge?: MessageDescriptor
	shown?: boolean
	onboardingId?: string
}

const { formatMessage } = useVIntl()

const props = withDefaults(
	defineProps<{
		tabs: Tab[]
		header?: string
		maxWidth?: string
		width?: string
		closable?: boolean
		onHide?: () => void
		onShow?: () => void
	}>(),
	{
		header: undefined,
		maxWidth: undefined,
		width: undefined,
		closable: true,
		onHide: undefined,
		onShow: undefined,
	},
)

const visibleTabs = computed(() => props.tabs.filter((tab) => tab.shown !== false))

const selectedTab = ref(0)

const scrollContainer = ref<HTMLElement | null>(null)
const { showTopFade, showBottomFade, checkScrollState, forceCheck } =
	useScrollIndicator(scrollContainer)

const modal = ref<InstanceType<typeof NewModal> | null>(null)

function setTab(index: number) {
	selectedTab.value = index
	nextTick(() => forceCheck())
}

function show(event?: MouseEvent) {
	modal.value?.show(event)
}

function hide() {
	modal.value?.hide()
}

defineExpose({ show, hide, selectedTab, setTab })
</script>
<template>
	<NewModal
		ref="modal"
		:header="header"
		:max-width="maxWidth"
		:width="width"
		:closable="closable"
		:on-hide="onHide"
		:on-show="onShow"
		no-padding
	>
		<template v-if="$slots.title" #title>
			<slot name="title" />
		</template>
		<div
			class="be-tabbed-shell grid min-h-0 grid-cols-[auto_minmax(0,1fr)] grid-rows-[minmax(0,1fr)] p-6 pb-3 pr-0"
		>
			<div
				class="be-tab-rail flex flex-col gap-1 border-solid pr-4 border-0 border-r-[1px] border-divider min-w-[200px]"
			>
				<component
					:is="tab.href ? 'a' : 'button'"
					v-for="(tab, index) in visibleTabs"
					:key="index"
					:href="tab.href ?? undefined"
					:data-onboarding-id="tab.onboardingId"
					:target="tab.href ? '_blank' : undefined"
					:rel="tab.href ? 'noopener noreferrer' : undefined"
					:class="`be-tab-control flex gap-2 items-center text-left px-4 py-2 border-none text-nowrap font-semibold cursor-pointer active:scale-[0.97] transition-all no-underline ${!tab.href && selectedTab === index ? 'is-active bg-button-bgSelected text-button-textSelected' : 'bg-transparent text-button-text hover:bg-button-bg hover:text-contrast'}`"
					@click="!tab.href && setTab(index)"
				>
					<component :is="tab.icon" class="w-4 h-4 flex-shrink-0" />
					<span>{{ formatMessage(tab.name) }}</span>
					<span
						v-if="tab.badge"
						class="rounded-full px-1.5 py-0.5 text-xs font-bold bg-brand-highlight text-brand"
					>
						{{ formatMessage(tab.badge) }}
					</span>
					<RightArrowIcon v-if="tab.href" class="size-4 ml-auto" />
				</component>

				<slot name="footer" />
			</div>
			<div class="be-tab-stage relative min-h-0 min-w-0 overflow-hidden">
				<Transition
					enter-active-class="transition-all duration-200 ease-out"
					enter-from-class="opacity-0 max-h-0"
					enter-to-class="opacity-100 max-h-4"
					leave-active-class="transition-all duration-200 ease-in"
					leave-from-class="opacity-100 max-h-4"
					leave-to-class="opacity-0 max-h-0"
				>
					<div
						v-if="showTopFade"
						class="pointer-events-none absolute left-0 right-0 top-0 z-10 h-4 bg-gradient-to-b from-bg-raised to-transparent"
					/>
				</Transition>

				<div
					ref="scrollContainer"
					class="h-screen min-h-0 max-h-[min(65vh,600px)]"
					:class="
						visibleTabs[selectedTab]?.flushContent ? 'overflow-hidden' : 'overflow-y-auto px-6 pb-6'
					"
					@scroll="checkScrollState"
				>
					<Suspense>
						<component
							:is="visibleTabs[selectedTab]?.content"
							v-if="visibleTabs[selectedTab]?.content"
						/>
					</Suspense>
				</div>

				<Transition
					enter-active-class="transition-all duration-200 ease-out"
					enter-from-class="opacity-0 max-h-0"
					enter-to-class="opacity-100 max-h-16"
					leave-active-class="transition-all duration-200 ease-in"
					leave-from-class="opacity-100 max-h-16"
					leave-to-class="opacity-0 max-h-0"
				>
					<div
						v-if="showBottomFade"
						class="pointer-events-none absolute bottom-0 left-0 right-0 z-10 h-16 bg-gradient-to-t from-bg-raised to-transparent"
					/>
				</Transition>
			</div>
		</div>
	</NewModal>
</template>

<style scoped>
.be-tabbed-shell {
	padding: 0;
}

.be-tab-rail {
	min-width: 14rem;
	padding: 1rem 0.7rem;
	border-right-color: var(--be-seam);
	background:
		repeating-linear-gradient(
			0deg,
			transparent 0 31px,
			color-mix(in srgb, var(--be-moss) 4%, transparent) 31px 32px
		),
		color-mix(in srgb, var(--color-button-bg) 72%, var(--be-deepslate) 6%);
}

.be-tab-control {
	position: relative;
	min-height: 2.65rem;
	border-radius: 0.42rem;
	font-size: 0.78rem;
}

.be-tab-control.is-active {
	box-shadow: inset 3px 0 var(--be-moss);
}

.be-tab-control :deep(svg) {
	color: var(--be-moss);
}

.be-tab-stage {
	background: var(--be-panel);
}

@media (max-width: 720px) {
	.be-tabbed-shell {
		grid-template-columns: 4.2rem minmax(0, 1fr);
	}

	.be-tab-rail {
		min-width: 0;
	}

	.be-tab-control {
		justify-content: center;
		padding-inline: 0.5rem;
	}

	.be-tab-control span {
		display: none;
	}
}
</style>
