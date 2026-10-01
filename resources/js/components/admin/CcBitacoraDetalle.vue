<script setup lang="ts">
import {
    cambiosDe,
    etiquetaAccion,
    etiquetaTipo,
    fraseEvento,
    iniciales,
    tituloCambios,
    tonoAccion,
    type EventoBitacora,
} from '@/lib/bitacoraLectura';
import { computed } from 'vue';

const props = defineProps<{
    evento: EventoBitacora;
}>();

const frase = computed(() => fraseEvento(props.evento));
const cambios = computed(() =>
    cambiosDe(props.evento.valor_anterior, props.evento.valor_nuevo),
);
const titulo = computed(() => tituloCambios(cambios.value));
const inicialesQuien = computed(() => iniciales(props.evento.usuario || 'S'));
</script>

<template>
    <div class="cc-log-detalle">
        <header class="cc-log-detalle__hero">
            <span class="cc-user-initials">{{ inicialesQuien }}</span>
            <h2 id="cc-log-ficha">{{ evento.usuario }}</h2>
            <p>{{ frase }}</p>
            <div class="cc-log-detalle__meta">
                <span
                    class="cc-log__badge"
                    :class="`cc-log__badge--${tonoAccion(evento.accion)}`"
                >
                    {{ etiquetaAccion(evento.accion) }}
                </span>
                <small>{{ evento.fecha }} · {{ evento.hora }}</small>
            </div>
        </header>

        <section v-if="cambios.length" class="cc-log-detalle__cambios">
            <h3>{{ titulo }}</h3>
            <article
                v-for="cambio in cambios"
                :key="cambio.clave"
                class="cc-log-cambio"
            >
                <span>{{ cambio.etiqueta }}</span>
                <b v-if="cambio.ahora">{{ cambio.ahora }}</b>
                <small v-if="cambio.antes && cambio.antes !== cambio.ahora">
                    antes {{ cambio.antes }}
                </small>
            </article>
        </section>

        <p v-else-if="evento.descripcion" class="cc-log-detalle__nota">
            {{ evento.descripcion }}
        </p>

        <dl class="cc-log-detalle__datos">
            <div v-if="evento.objetivo">
                <dt>{{ etiquetaTipo(evento.tipo_entidad) }}</dt>
                <dd>{{ evento.objetivo }}</dd>
            </div>
            <div>
                <dt>Módulo</dt>
                <dd>{{ evento.modulo }}</dd>
            </div>
            <div v-if="evento.direccion_ip">
                <dt>IP</dt>
                <dd>{{ evento.direccion_ip }}</dd>
            </div>
        </dl>
    </div>
</template>
