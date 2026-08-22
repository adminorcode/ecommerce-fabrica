const DEFAULT_DURATION_SECONDS = 10;
const MIN_DURATION_SECONDS = 3;
const MAX_DURATION_SECONDS = 60;
const SWIPE_THRESHOLD_PX = 48;
const REDUCED_MOTION = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function durationMs(slide) {
	const raw = Number.parseInt(slide?.dataset.durationSeconds || '', 10);
	if (!Number.isFinite(raw) || raw < 1) {
		return DEFAULT_DURATION_SECONDS * 1000;
	}

	return Math.min(MAX_DURATION_SECONDS, Math.max(MIN_DURATION_SECONDS, raw)) * 1000;
}

function getCarousel(root) {
	return {
		root,
		track: root.querySelector('.petshop-home-campaigns__track'),
		slides: [...root.querySelectorAll('.petshop-home-campaigns__slide')],
		prev: root.querySelector('.petshop-home-campaigns__prev'),
		next: root.querySelector('.petshop-home-campaigns__next'),
		dots: [...root.querySelectorAll('.petshop-home-campaigns__dot')],
		status: root.querySelector('.petshop-home-campaigns__status'),
		index: 0,
		timer: 0,
		paused: false,
		hovering: false,
		focused: false,
	};
}

function announce(carousel, index) {
	if (!carousel.status) {
		return;
	}

	const total = carousel.slides.length;
	carousel.status.textContent = `${index + 1} / ${total}`;
}

function stopTimer(carousel) {
	if (carousel.timer) {
		window.clearTimeout(carousel.timer);
		carousel.timer = 0;
	}
}

function schedule(carousel) {
	stopTimer(carousel);
	if (REDUCED_MOTION || carousel.paused || carousel.slides.length < 2) {
		return;
	}

	const activeSlide = carousel.slides[carousel.index];
	carousel.timer = window.setTimeout(() => {
		setActiveSlide(carousel, carousel.index + 1, { announce: false });
		schedule(carousel);
	}, durationMs(activeSlide));
}

function pauseAutoplay(carousel) {
	carousel.paused = true;
	stopTimer(carousel);
}

function maybeResume(carousel) {
	if (carousel.hovering || carousel.focused || document.hidden) {
		return;
	}

	carousel.paused = false;
	schedule(carousel);
}

function setActiveSlide(carousel, nextIndex, options = {}) {
	const { announce: shouldAnnounce = true } = options;
	const total = carousel.slides.length;
	if (total === 0) {
		return;
	}

	const index = ((nextIndex % total) + total) % total;
	carousel.index = index;

	carousel.slides.forEach((slide, slideIndex) => {
		const isActive = slideIndex === index;
		slide.hidden = !isActive;
		slide.setAttribute('aria-hidden', isActive ? 'false' : 'true');
	});

	carousel.dots.forEach((dot, dotIndex) => {
		dot.setAttribute('aria-selected', dotIndex === index ? 'true' : 'false');
		dot.classList.toggle('is-active', dotIndex === index);
	});

	if (carousel.prev instanceof HTMLButtonElement) {
		carousel.prev.disabled = total <= 1;
	}

	if (carousel.next instanceof HTMLButtonElement) {
		carousel.next.disabled = total <= 1;
	}

	if (shouldAnnounce) {
		announce(carousel, index);
	}
}

function bindCarousel(root) {
	if (root.dataset.petshopCampaignsReady === 'true') {
		return;
	}

	const carousel = getCarousel(root);
	if (carousel.slides.length <= 1) {
		root.dataset.petshopCampaignsReady = 'true';
		return;
	}

	const goTo = (index, options = {}) => {
		setActiveSlide(carousel, index, options);
		if (!carousel.paused) {
			schedule(carousel);
		}
	};

	carousel.prev?.addEventListener('click', () => {
		goTo(carousel.index - 1);
	});

	carousel.next?.addEventListener('click', () => {
		goTo(carousel.index + 1);
	});

	carousel.dots.forEach((dot, dotIndex) => {
		dot.addEventListener('click', () => {
			goTo(dotIndex);
		});
	});

	root.addEventListener('keydown', (event) => {
		const target = event.target;
		if (!(target instanceof HTMLElement) || !root.contains(target)) {
			return;
		}

		const isControl = target.matches('.petshop-home-campaigns__prev, .petshop-home-campaigns__next, .petshop-home-campaigns__dot');
		if (!isControl) {
			return;
		}

		if (event.key === 'ArrowLeft') {
			event.preventDefault();
			goTo(carousel.index - 1);
		}

		if (event.key === 'ArrowRight') {
			event.preventDefault();
			goTo(carousel.index + 1);
		}
	});

	root.addEventListener('mouseenter', () => {
		carousel.hovering = true;
		pauseAutoplay(carousel);
	});

	root.addEventListener('mouseleave', () => {
		carousel.hovering = false;
		maybeResume(carousel);
	});

	root.addEventListener('focusin', () => {
		carousel.focused = true;
		pauseAutoplay(carousel);
	});

	root.addEventListener('focusout', (event) => {
		const next = event.relatedTarget;
		carousel.focused = next instanceof Node && root.contains(next);
		if (!carousel.focused) {
			maybeResume(carousel);
		}
	});

	document.addEventListener('visibilitychange', () => {
		if (document.hidden) {
			pauseAutoplay(carousel);
			return;
		}

		maybeResume(carousel);
	});

	if (carousel.track instanceof HTMLElement) {
		let pointerStartX = null;

		carousel.track.addEventListener('pointerdown', (event) => {
			if (event.pointerType === 'mouse' && event.button !== 0) {
				return;
			}

			pointerStartX = event.clientX;
		});

		carousel.track.addEventListener('pointerup', (event) => {
			if (pointerStartX === null) {
				return;
			}

			const delta = event.clientX - pointerStartX;
			pointerStartX = null;
			if (Math.abs(delta) < SWIPE_THRESHOLD_PX) {
				return;
			}

			goTo(carousel.index + (delta < 0 ? 1 : -1));
		});

		carousel.track.addEventListener('pointercancel', () => {
			pointerStartX = null;
		});
	}

	setActiveSlide(carousel, 0, { announce: false });
	schedule(carousel);
	root.dataset.petshopCampaignsReady = 'true';
}

function initCarousels() {
	document.querySelectorAll('.petshop-home-campaigns.is-carousel').forEach((root) => {
		if (root instanceof HTMLElement) {
			bindCarousel(root);
		}
	});
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initCarousels, { once: true });
} else {
	initCarousels();
}

if (REDUCED_MOTION) {
	document.documentElement.classList.add('petshop-reduced-motion');
}
