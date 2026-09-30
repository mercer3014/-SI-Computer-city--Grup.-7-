<script setup lang="ts">
import confetti from 'canvas-confetti';
import { onBeforeUnmount, onMounted, ref } from 'vue';

/** Colores Computer City — misma idea que Magic UI / canvas-confetti. */
const COLORS = ['#FF3D8F', '#B01050', '#5CE1FF', '#D97706', '#3DDC97', '#FFD166'];

type ConfettiInstance = ReturnType<typeof confetti.create>;

const canvas = ref<HTMLCanvasElement | null>(null);
let instance: ConfettiInstance | null = null;
let rafId = 0;
let timeoutIds: number[] = [];

function clearTimers(): void {
    if (rafId) {
        cancelAnimationFrame(rafId);
        rafId = 0;
    }

    for (const id of timeoutIds) {
        window.clearTimeout(id);
    }

    timeoutIds = [];
}

function headingOrigin(): { x: number; y: number } {
    const heading = document.querySelector('.cc-form--success h1');

    if (!heading) {
        return { x: 0.5, y: 0.35 };
    }

    const box = heading.getBoundingClientRect();

    return {
        x: (box.left + box.width / 2) / window.innerWidth,
        y: (box.top + box.height / 2) / window.innerHeight,
    };
}

function fireBurst(origin: { x: number; y: number }): void {
    if (!instance) {
        return;
    }

    instance({
        particleCount: 90,
        spread: 70,
        startVelocity: 48,
        gravity: 1.05,
        ticks: 220,
        origin,
        colors: COLORS,
        zIndex: 40,
    });

    instance({
        particleCount: 40,
        spread: 100,
        startVelocity: 28,
        gravity: 0.9,
        ticks: 180,
        origin,
        colors: COLORS,
        scalar: 0.85,
        zIndex: 40,
    });
}

/** Side cannons ~1.8s — patrón de Magic UI. */
function fireSideCannons(): void {
    if (!instance) {
        return;
    }

    const end = Date.now() + 1800;

    const frame = () => {
        if (!instance || Date.now() > end) {
            return;
        }

        instance({
            particleCount: 3,
            angle: 60,
            spread: 55,
            startVelocity: 55,
            origin: { x: 0, y: 0.55 },
            colors: COLORS,
            zIndex: 40,
        });

        instance({
            particleCount: 3,
            angle: 120,
            spread: 55,
            startVelocity: 55,
            origin: { x: 1, y: 0.55 },
            colors: COLORS,
            zIndex: 40,
        });

        rafId = requestAnimationFrame(frame);
    };

    frame();
}

function celebrate(): void {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    if (!canvas.value) {
        return;
    }

    clearTimers();

    instance ??= confetti.create(canvas.value, {
        resize: true,
        useWorker: true,
    });

    const origin = headingOrigin();
    fireBurst(origin);
    fireSideCannons();

    timeoutIds.push(
        window.setTimeout(() => {
            fireBurst({
                x: origin.x,
                y: Math.max(0.12, origin.y - 0.08),
            });
        }, 280),
    );
}

onMounted(() => {
    timeoutIds.push(window.setTimeout(celebrate, 40));
});

onBeforeUnmount(() => {
    clearTimers();
    instance?.reset();
    instance = null;
});
</script>

<template>
    <!-- Teleport: evita que motion/layout (transform) atrape position:fixed -->
    <Teleport to="body">
        <canvas ref="canvas" class="cc-confetti" aria-hidden="true" />
    </Teleport>
</template>
