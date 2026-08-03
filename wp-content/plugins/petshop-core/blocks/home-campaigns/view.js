const REDUCED_MOTION = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

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
	};
}

function announce(carousel, index) {
	if (!carousel.status) {
		return;
	}

	const total = carousel.slides.length;
	carousel.status.textContent = `${index + 1} / ${total}`;
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

	const goTo = (index) => {
		setActiveSlide(carousel, index);
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

	setActiveSlide(carousel, 0, { announce: false });
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
