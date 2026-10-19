// header.js
// ไฟล์นี้ใช้ควบคุมเมนูเกี่ยวกับชมรม เมนูผู้ใช้ และเมนูมือถือ


document.addEventListener('DOMContentLoaded', function () {


    // =========================================
    // ABOUT DROPDOWN
    // =========================================

    const aboutDropdown =
        document.getElementById('bcAboutDropdown');

    const aboutButton =
        document.getElementById('bcAboutBtn');


    // =========================================
    // USER DROPDOWN
    // =========================================

    const userDropdown =
        document.getElementById('bcUserDropdown');

    const userButton =
        document.getElementById('bcUserBtn');


    // =========================================
    // MOBILE MENU
    // =========================================

    const mobileButton =
        document.getElementById('bcMobileBtn');

    const navArea =
        document.getElementById('bcNavArea');


    // =========================================
    // เปิด / ปิด ABOUT
    // =========================================

    if (
        aboutDropdown &&
        aboutButton
    ) {

        aboutButton.addEventListener(
            'click',
            function (event) {

                event.preventDefault();
                event.stopPropagation();


                const isOpen =
                    aboutDropdown.classList.contains('open');


                // ปิด User ก่อน
                if (userDropdown) {
                    userDropdown.classList.remove('open');
                }

                if (userButton) {
                    userButton.setAttribute(
                        'aria-expanded',
                        'false'
                    );
                }


                // Toggle About
                if (isOpen) {

                    aboutDropdown.classList.remove('open');

                    aboutButton.setAttribute(
                        'aria-expanded',
                        'false'
                    );

                } else {

                    aboutDropdown.classList.add('open');

                    aboutButton.setAttribute(
                        'aria-expanded',
                        'true'
                    );

                }

            }
        );

    }


    // =========================================
    // เปิด / ปิด USER MENU
    // =========================================

    if (
        userDropdown &&
        userButton
    ) {

        userButton.addEventListener(
            'click',
            function (event) {

                event.preventDefault();
                event.stopPropagation();


                const isOpen =
                    userDropdown.classList.contains('open');


                // ปิด About ก่อน
                if (aboutDropdown) {
                    aboutDropdown.classList.remove('open');
                }

                if (aboutButton) {
                    aboutButton.setAttribute(
                        'aria-expanded',
                        'false'
                    );
                }


                // Toggle User
                if (isOpen) {

                    userDropdown.classList.remove('open');

                    userButton.setAttribute(
                        'aria-expanded',
                        'false'
                    );

                } else {

                    userDropdown.classList.add('open');

                    userButton.setAttribute(
                        'aria-expanded',
                        'true'
                    );

                }

            }
        );

    }


    // =========================================
    // MOBILE MENU
    // =========================================

    if (
        mobileButton &&
        navArea
    ) {

        mobileButton.addEventListener(
            'click',
            function (event) {

                event.preventDefault();
                event.stopPropagation();

                navArea.classList.toggle('open');


                const icon =
                    mobileButton.querySelector('i');


                if (icon) {

                    if (
                        navArea.classList.contains('open')
                    ) {

                        icon.classList.remove('fa-bars');
                        icon.classList.add('fa-xmark');

                    } else {

                        icon.classList.remove('fa-xmark');
                        icon.classList.add('fa-bars');

                    }

                }

            }
        );

    }


    // =========================================
    // คลิกใน Dropdown
    // ไม่ให้ Document ปิดทันที
    // =========================================

    if (aboutDropdown) {

        aboutDropdown.addEventListener(
            'click',
            function (event) {

                event.stopPropagation();

            }
        );

    }


    if (userDropdown) {

        userDropdown.addEventListener(
            'click',
            function (event) {

                event.stopPropagation();

            }
        );

    }


    if (navArea) {

        navArea.addEventListener(
            'click',
            function (event) {

                event.stopPropagation();

            }
        );

    }


    // =========================================
    // CLICK OUTSIDE
    // =========================================

    document.addEventListener(
        'click',
        function () {


            // ปิด About
            if (aboutDropdown) {

                aboutDropdown.classList.remove('open');

            }

            if (aboutButton) {

                aboutButton.setAttribute(
                    'aria-expanded',
                    'false'
                );

            }


            // ปิด User
            if (userDropdown) {

                userDropdown.classList.remove('open');

            }

            if (userButton) {

                userButton.setAttribute(
                    'aria-expanded',
                    'false'
                );

            }


            // ปิด Mobile
            if (
                navArea &&
                window.innerWidth <= 900
            ) {

                navArea.classList.remove('open');


                if (mobileButton) {

                    const icon =
                        mobileButton.querySelector('i');


                    if (icon) {

                        icon.classList.remove('fa-xmark');
                        icon.classList.add('fa-bars');

                    }

                }

            }

        }
    );


    // =========================================
    // ESC
    // =========================================

    document.addEventListener(
        'keydown',
        function (event) {

            if (event.key !== 'Escape') {
                return;
            }


            if (aboutDropdown) {
                aboutDropdown.classList.remove('open');
            }

            if (aboutButton) {
                aboutButton.setAttribute(
                    'aria-expanded',
                    'false'
                );
            }


            if (userDropdown) {
                userDropdown.classList.remove('open');
            }

            if (userButton) {
                userButton.setAttribute(
                    'aria-expanded',
                    'false'
                );
            }


            if (navArea) {
                navArea.classList.remove('open');
            }


            if (mobileButton) {

                const icon =
                    mobileButton.querySelector('i');

                if (icon) {

                    icon.classList.remove('fa-xmark');
                    icon.classList.add('fa-bars');

                }

            }

        }
    );


    // =========================================
    // RESIZE
    // =========================================

    window.addEventListener(
        'resize',
        function () {

            if (
                window.innerWidth > 900 &&
                navArea
            ) {

                navArea.classList.remove('open');

            }


            if (mobileButton) {

                const icon =
                    mobileButton.querySelector('i');

                if (
                    icon &&
                    window.innerWidth > 900
                ) {

                    icon.classList.remove('fa-xmark');
                    icon.classList.add('fa-bars');

                }

            }

        }
    );

});