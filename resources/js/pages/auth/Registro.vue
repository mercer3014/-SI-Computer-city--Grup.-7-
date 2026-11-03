<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import AuthActionIcon from '@/components/AuthActionIcon.vue';
import AuthFieldHint from '@/components/AuthFieldHint.vue';
import AuthPasswordControl from '@/components/AuthPasswordControl.vue';
import TextLink from '@/components/TextLink.vue';
import { Spinner } from '@/components/ui/spinner';

defineOptions({
    layout: {
        title: 'Crear cuenta',
        description: 'Registrate para comprar en la tienda en línea',
        formSide: 'right',
    },
});

defineProps<{
    desdeTienda: boolean;
}>();
</script>

<template>
    <Head title="Crear cuenta" />

    <div v-if="desdeTienda" class="cc-status cc-status--success" role="status">
        Creá tu cuenta y seguimos con el pago de tu carrito.
    </div>

    <Form
        action="/registro"
        method="post"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="cc-form"
    >
        <h1>Crear cuenta</h1>

        <label class="cc-field">
            <span>Nombre completo</span>
            <span class="cc-field__control">
                <input name="nombre" type="text" maxlength="200" autocomplete="name" required v-focus />
            </span>
            <AuthFieldHint hint="Como figura en tu documento" :error="errors.nombre" />
        </label>

        <div class="cc-field-row">
            <label class="cc-field cc-field--corto">
                <span>Documento</span>
                <span class="cc-field__control">
                    <select name="tipo_documento" required>
                        <option value="CI">CI</option>
                        <option value="NIT">NIT</option>
                        <option value="PASAPORTE">Pasaporte</option>
                    </select>
                </span>
            </label>
            <label class="cc-field">
                <span>Número</span>
                <span class="cc-field__control">
                    <input name="numero_documento" type="text" maxlength="50" required />
                </span>
                <AuthFieldHint hint="Para tu comprobante y garantía" :error="errors.numero_documento" />
            </label>
        </div>

        <label class="cc-field">
            <span>Teléfono</span>
            <span class="cc-field__control">
                <input name="telefono" type="tel" maxlength="15" inputmode="tel" autocomplete="tel" />
            </span>
            <AuthFieldHint hint="Opcional · te avisamos cuando tu pedido esté listo" :error="errors.telefono" />
        </label>

        <label class="cc-field">
            <span>Correo</span>
            <span class="cc-field__control">
                <input name="email" type="email" maxlength="150" autocomplete="email" required />
            </span>
            <AuthFieldHint hint="Con este correo vas a iniciar sesión" :error="errors.email" />
        </label>

        <label class="cc-field">
            <span>Clave</span>
            <AuthPasswordControl id="password" name="password" autocomplete="new-password" />
            <AuthFieldHint
                hint="8+ caracteres con mayúscula, minúscula, número y símbolo"
                :error="errors.password"
            />
        </label>

        <label class="cc-field">
            <span>Repetí la clave</span>
            <AuthPasswordControl id="password_confirmation" name="password_confirmation" autocomplete="new-password" />
        </label>

        <div class="cc-auth-actions">
            <button type="submit" class="cc-button" :disabled="processing">
                <Spinner v-if="processing" />
                <AuthActionIcon v-else />
                <span>Crear cuenta</span>
            </button>
            <div class="cc-auth-links">
                <TextLink href="/login">Ya tengo cuenta</TextLink>
                <TextLink href="/tienda">Volver a la tienda</TextLink>
            </div>
        </div>
    </Form>
</template>
