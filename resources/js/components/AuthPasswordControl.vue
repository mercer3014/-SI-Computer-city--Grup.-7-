<script setup lang="ts">
import { Eye, EyeOff } from '@lucide/vue';
import { ref } from 'vue';

withDefaults(
    defineProps<{
        id: string;
        name: string;
        autocomplete?: string;
        required?: boolean;
        passwordrules?: string;
        autofocus?: boolean;
        tabindex?: number | string;
        disabled?: boolean;
    }>(),
    {
        autocomplete: 'new-password',
        required: true,
        autofocus: false,
        disabled: false,
    },
);

const visible = ref(false);
</script>

<template>
    <span class="cc-field__control">
        <input
            :id="id"
            :type="visible ? 'text' : 'password'"
            :name="name"
            :autocomplete="autocomplete"
            :required="required"
            :passwordrules="passwordrules"
            :tabindex="tabindex"
            :disabled="disabled"
            v-focus="autofocus"
        />
        <button
            type="button"
            class="cc-field__reveal"
            :aria-label="visible ? 'Ocultar clave' : 'Ver clave'"
            :aria-pressed="visible"
            :disabled="disabled"
            tabindex="-1"
            @click="visible = !visible"
        >
            <EyeOff v-if="visible" class="cc-field__reveal-icon" />
            <Eye v-else class="cc-field__reveal-icon" />
        </button>
    </span>
</template>
