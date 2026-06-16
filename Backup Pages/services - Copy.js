/* const hamburger = document.querySelector('.hamburger');
const mobileNav = document.querySelector('.mobile-nav');
const bookingPopup = document.querySelector('.booking-popup');
const closePopupButtons = document.querySelectorAll('.close-popup, .popup-overlay');
const bookingButtons = document.querySelectorAll('.js-book');

if (hamburger && mobileNav) {
    hamburger.addEventListener('click', () => {
        mobileNav.classList.toggle('open');
    });
}

bookingButtons.forEach((button) => {
    button.addEventListener('click', () => {
        if (bookingPopup) {
            bookingPopup.classList.add('active');
        }
    });
});

closePopupButtons.forEach((element) => {
    element.addEventListener('click', () => {
        if (bookingPopup) {
            bookingPopup.classList.remove('active');
        }
    });
}); */
document.addEventListener("DOMContentLoaded", () => {
	const hamburger = document.getElementById("hamburger");
	const mobileNav = document.getElementById("mobileNav");
	const overlay = document.getElementById("navOverlay");
	const mobileBookBtn = document.getElementById("mobileBookBtn");

	if (!hamburger || !mobileNav || !overlay) return;

	function openMenu() {
		hamburger.classList.add("active");
		mobileNav.classList.add("active");
		overlay.classList.add("active");
		document.body.classList.add("menu-open");
		document.body.style.overflow = "hidden";
	}

	function closeMenu() {
		hamburger.classList.remove("active");
		mobileNav.classList.remove("active");
		overlay.classList.remove("active");
		document.body.classList.remove("menu-open");
		document.body.style.overflow = "";
	}

	hamburger.addEventListener("click", e => {
		e.stopPropagation();
		if (mobileNav.classList.contains("active")) {
			closeMenu();
		} else {
			openMenu();
		}
	});

	overlay.addEventListener("click", closeMenu);

	document.querySelectorAll(".mobile-nav a").forEach(link => {
		link.addEventListener("click", closeMenu);
	});

	if (mobileBookBtn) {
		mobileBookBtn.addEventListener("click", e => {
			e.preventDefault();
			closeMenu();
			setTimeout(() => {
				openBooking();
			}, 180);
		});
	}

	window.addEventListener("resize", () => {
		if (window.innerWidth > 768) {
			closeMenu();
		}
	});
});
