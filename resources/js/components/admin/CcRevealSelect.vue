<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { AnimatePresence, motion } from 'motion-v';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { adminSpring } from '@/lib/adminMotion';

export type CcOpcion = { value: string; label: string };

const props = defineProps<{
    modelValue: string;
    opciones: CcOpcion[];
    etiqueta: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
    change: [];
}>();

const abierto = ref(false);
const raiz = ref<HTMLElement | null>(null);

const elegido = computed(() => String(props.modelValue ?? ''));

const texto = computed(
    () =>
        props.opciones.find((opcion) => opcion.value === elegido.value)
            ?.label ?? props.etiqueta,
);

function elegir(valor: string): void {
    emit('update:modelValue', valor);
    emit('change');
    abierto.value = false;
}

function afuera(evento: PointerEvent): void {
    if (!raiz.value?.contains(evento.target as Node)) {
        abierto.value = false;
    }
}

function tecla(evento: KeyboardEvent): void {
    if (evento.key === 'Escape') {
        abierto.value = false;
    }
}

onMounted(() => {
    document.addEventListener('pointerdown', afuera);
    document.addEventListener('keydown', tecla);
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', afuera);
    document.removeEventListener('keydown', tecla);
});
</script>

<template>
    <div ref="raiz" class="cc-reveal" :class="{ 'is-open': abierto }">
        <button
            type="button"
            class="cc-log__control cc-reveal__btn"
            :aria-expanded="abierto"
            aria-haspopup="listbox"
            @click="abierto = !abierto"
        >
            <span class="cc-reveal__value">{{ texto }}</span>
            <motion.span
                class="cc-reveal__chev"
                :animate="{ rotate: abierto ? 180 : 0 }"
                :transition="adminSpring"
            >
                <ChevronDown :size="16" />
            </motion.span>
        </button>

        <AnimatePresence>
            <motion.div
                v-if="abierto"
                key="lista"
                class="cc-reveal__panel"
                :initial="{ height: 0 }"
                :animate="{ height: 'auto' }"
                :exit="{ height: 0 }"
                :transition="adminSpring"
            >
                <ul class="cc-reveal__list" role="listbox">
                    <li
                        v-for="opcion in opciones"
                        :key="opcion.value || 'todas'"
                    >
                        <button
                            type="button"
                            role="option"
                            :aria-selected="opcion.value === elegido"
                            :class="{ 'is-on': opcion.value === elegido }"
                            @click="elegir(opcion.value)"
                        >
                            {{ opcion.label }}
                        </button>
                    </li>
                </ul>
            </motion.div>
        </AnimatePresence>
    </div>
</template>
