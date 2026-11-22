<script setup lang="ts">
import { computed } from 'vue';
import {
    abreviar,
    formatearMonto,
    PALETA,
    techoRedondo,
} from '@/lib/formatoReporte';
import type { SerieGrafico } from '@/lib/formatoReporte';

const props = withDefaults(
    defineProps<{
        etiquetas: string[];
        series: SerieGrafico[];
        moneda?: boolean;
    }>(),
    { moneda: false },
);

const ANCHO = 640;
const ALTO = 300;
const MARGEN = { arriba: 16, derecha: 20, abajo: 56, izquierda: 56 };
const AREA_ANCHO = ANCHO - MARGEN.izquierda - MARGEN.derecha;
const AREA_ALTO = ALTO - MARGEN.arriba - MARGEN.abajo;

const todos = computed(() => props.series.flatMap((s) => s.valores));
const maximo = computed(() => techoRedondo(Math.max(0, ...todos.value)));
const minimo = computed(() => {
    const menor = Math.min(0, ...todos.value);

    return menor < 0 ? -techoRedondo(-menor) : 0;
});

const escalaY = (valor: number): number =>
    MARGEN.arriba +
    AREA_ALTO * (1 - (valor - minimo.value) / (maximo.value - minimo.value));

const escalaX = (indice: number): number =>
    props.etiquetas.length <= 1
        ? MARGEN.izquierda + AREA_ANCHO / 2
        : MARGEN.izquierda +
          (AREA_ANCHO * indice) / (props.etiquetas.length - 1);

const guias = computed(() =>
    Array.from({ length: 5 }, (_, i) => {
        const valor = minimo.value + ((maximo.value - minimo.value) * i) / 4;

        return { valor, y: escalaY(valor) };
    }),
);

const lineas = computed(() =>
    props.series.map((serie, indiceSerie) => ({
        nombre: serie.nombre,
        color: PALETA[indiceSerie % PALETA.length],
        trazo: serie.valores
            .map((valor, indice) => `${escalaX(indice)},${escalaY(valor)}`)
            .join(' '),
        puntos: serie.valores.map((valor, indice) => ({
            x: escalaX(indice),
            y: escalaY(valor),
            titulo: `${serie.nombre} · ${props.etiquetas[indice] ?? ''}: ${
                props.moneda ? formatearMonto(valor) : valor
            }`,
        })),
    })),
);

const paso = computed(() => Math.max(1, Math.ceil(props.etiquetas.length / 8)));
const visibles = computed(() =>
    props.etiquetas
        .map((texto, indice) => ({ texto, indice }))
        .filter(({ indice }) => indice % paso.value === 0),
);
</script>

<template>
    <figure class="cc-grafico">
        <svg
            :viewBox="`0 0 ${ANCHO} ${ALTO}`"
            role="img"
            aria-label="Gráfico de líneas del reporte"
        >
            <g class="cc-grafico__guias">
                <g v-for="guia in guias" :key="guia.valor">
                    <line
                        :x1="MARGEN.izquierda"
                        :x2="ANCHO - MARGEN.derecha"
                        :y1="guia.y"
                        :y2="guia.y"
                    />
                    <text
                        :x="MARGEN.izquierda - 8"
                        :y="guia.y + 4"
                        text-anchor="end"
                    >
                        {{ abreviar(guia.valor) }}
                    </text>
                </g>
            </g>
            <g v-for="linea in lineas" :key="linea.nombre">
                <polyline
                    :points="linea.trazo"
                    fill="none"
                    :stroke="linea.color"
                    stroke-width="2.5"
                    stroke-linejoin="round"
                />
                <circle
                    v-for="punto in linea.puntos"
                    :key="`${punto.x}-${punto.y}`"
                    :cx="punto.x"
                    :cy="punto.y"
                    r="3.5"
                    :fill="linea.color"
                >
                    <title>{{ punto.titulo }}</title>
                </circle>
            </g>
            <g class="cc-grafico__etiquetas">
                <text
                    v-for="etiqueta in visibles"
                    :key="etiqueta.indice"
                    :x="escalaX(etiqueta.indice)"
                    :y="ALTO - MARGEN.abajo + 20"
                    text-anchor="middle"
                >
                    {{ etiqueta.texto }}
                </text>
            </g>
        </svg>
        <figcaption v-if="series.length > 1" class="cc-grafico__leyenda">
            <span v-for="(serie, indice) in series" :key="serie.nombre">
                <i :style="{ background: PALETA[indice % PALETA.length] }" />
                {{ serie.nombre }}
            </span>
        </figcaption>
    </figure>
</template>

<style scoped>
.cc-grafico {
    margin: 0;
}

.cc-grafico svg {
    display: block;
    width: 100%;
    height: auto;
}

.cc-grafico__guias line {
    stroke: var(--cc-border);
    stroke-dasharray: 3 4;
}

.cc-grafico text {
    fill: var(--cc-muted);
    font-size: 11px;
}

.cc-grafico__leyenda {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin-top: 8px;
    color: var(--cc-muted);
    font-size: 12px;
}

.cc-grafico__leyenda i {
    display: inline-block;
    width: 10px;
    height: 10px;
    margin-right: 6px;
    border-radius: 2px;
}
</style>
