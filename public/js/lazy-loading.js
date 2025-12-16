/**
 * 微信图片懒加载处理脚本
 * 支持 IntersectionObserver API 和传统滚动事件的降级处理
 */

(function () {
    'use strict';

    // 配置参数
    const config = {
        rootMargin: '50px 0px', // 提前50px开始加载
        threshold: 0.1,
        loadingClass: 'img-loading',
        errorClass: 'img-error',
        loadedClass: 'img-loaded'
    };

    // 检查 IntersectionObserver 支持
    const hasIntersectionObserver = 'IntersectionObserver' in window &&
        'IntersectionObserverEntry' in window;

    /**
     * 创建加载中的占位图片
     */
    function createLoadingPlaceholder() {
        return 'data:image/svg+xml;base64,' + btoa(`
            <svg width="200" height="150" xmlns="http://www.w3.org/2000/svg">
                <rect width="100%" height="100%" fill="#f5f5f5"/>
                <text x="50%" y="50%" text-anchor="middle" dy=".3em" fill="#999" font-size="14">
                    Loading...
                </text>
            </svg>
        `);
    }

    /**
     * 创建错误占位图片
     */
    function createErrorPlaceholder() {
        return 'data:image/svg+xml;base64,' + btoa(`
            <svg width="200" height="150" xmlns="http://www.w3.org/2000/svg">
                <rect width="100%" height="100%" fill="#ffebee"/>
                <text x="50%" y="50%" text-anchor="middle" dy=".3em" fill="#c62828" font-size="14">
                    Image Load Failed
                </text>
            </svg>
        `);
    }

    /**
     * 预加载图片
     */
    function preloadImage(src) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.onload = () => resolve(src);
            img.onerror = () => reject(new Error(`Failed to load image: ${src}`));
            img.src = src;
        });
    }

    /**
     * 处理单个图片的懒加载
     */
    function handleImage(img) {
        const dataSrc = img.getAttribute('data-src');
        if (!dataSrc || img.src === dataSrc) {
            return; // 已经加载或没有需要懒加载的图片
        }

        // 添加加载状态
        img.classList.add(config.loadingClass);

        // 设置临时占位图
        if (!img.src || img.src === window.location.href) {
            img.src = createLoadingPlaceholder();
        }

        // 预加载图片
        preloadImage(dataSrc)
            .then(() => {
                img.src = dataSrc;
                img.classList.remove(config.loadingClass);
                img.classList.add(config.loadedClass);

                // 移除 data-src 属性，避免重复处理
                img.removeAttribute('data-src');

                // 触发自定义事件
                img.dispatchEvent(new CustomEvent('imageloaded', {
                    detail: { src: dataSrc }
                }));
            })
            .catch(() => {
                img.src = createErrorPlaceholder();
                img.classList.remove(config.loadingClass);
                img.classList.add(config.errorClass);

                // 触发错误事件
                img.dispatchEvent(new CustomEvent('imageerror', {
                    detail: { src: dataSrc }
                }));
            });
    }

    /**
     * 使用 IntersectionObserver 实现懒加载
     */
    function createIntersectionObserver() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    handleImage(img);
                    observer.unobserve(img); // 停止观察已加载的图片
                }
            });
        }, {
            rootMargin: config.rootMargin,
            threshold: config.threshold
        });

        return observer;
    }

    /**
     * 传统滚动事件处理（降级方案）
     */
    function handleScrollObserver() {
        let ticking = false;

        function updateImages() {
            const images = document.querySelectorAll('img[data-src]');
            const viewportHeight = window.innerHeight;

            images.forEach(img => {
                const rect = img.getBoundingClientRect();
                const margin = parseInt(config.rootMargin) || 50;

                // 检查图片是否在视口范围内（包含边距）
                if (rect.top >= -margin && rect.top <= viewportHeight + margin) {
                    handleImage(img);
                }
            });

            ticking = false;
        }

        function onScroll() {
            if (!ticking) {
                requestAnimationFrame(updateImages);
                ticking = true;
            }
        }

        window.addEventListener('scroll', onScroll);
        window.addEventListener('resize', onScroll);

        // 初始检查
        updateImages();
    }

    /**
     * 初始化懒加载
     */
    function initLazyLoading() {
        // 查找所有需要懒加载的图片
        const images = document.querySelectorAll('img[data-src]');

        if (images.length === 0) {
            return; // 没有需要懒加载的图片
        }

        console.log(`发现 ${images.length} 张需要懒加载的图片`);

        if (hasIntersectionObserver) {
            console.log('使用 IntersectionObserver 实现懒加载');
            const observer = createIntersectionObserver();

            images.forEach(img => {
                // 为图片添加加载状态样式
                img.classList.add(config.loadingClass);
                observer.observe(img);
            });
        } else {
            console.log('使用传统滚动事件实现懒加载');
            handleScrollObserver();
        }
    }

    /**
     * 处理微信编辑器图片的特殊类名
     */
    function processWechatImages() {
        // 处理微信编辑器的各种图片类名
        const wechatSelectors = [
            '.rich_pages img',
            '.wxw-img',
            '.js_insertlocalimg',
            '[data-src]'
        ];

        wechatSelectors.forEach(selector => {
            const images = document.querySelectorAll(selector);
            images.forEach(img => {
                // 如果有 data-src 但没有 src，设置为懒加载
                if (img.hasAttribute('data-src') && (!img.src || img.src === window.location.href)) {
                    img.classList.add('wechat-lazy-image');
                }

                // 确保微信编辑器图片有正确的样式类
                if (!img.classList.contains('wxw-img') &&
                    (img.closest('.rich_pages') || img.classList.contains('js_insertlocalimg'))) {
                    img.classList.add('wxw-img');
                }
            });
        });
    }

    /**
     * 页面加载完成后初始化
     */
    function initialize() {
        // 处理微信编辑器图片
        processWechatImages();

        // 初始化懒加载
        initLazyLoading();

        // 监听动态添加的内容
        if (window.MutationObserver) {
            const mutationObserver = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.type === 'childList') {
                        mutation.addedNodes.forEach((node) => {
                            if (node.nodeType === Node.ELEMENT_NODE) {
                                // 检查新添加的节点中是否包含图片
                                const images = node.querySelectorAll ?
                                    node.querySelectorAll('img[data-src]') : [];

                                if (images.length > 0) {
                                    // 重新初始化懒加载
                                    setTimeout(initLazyLoading, 100);
                                }
                            }
                        });
                    }
                });
            });

            mutationObserver.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
    }

    // DOM 加载完成后初始化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }

    // 导出到全局作用域，方便外部调用
    window.WechatLazyLoader = {
        init: initLazyLoading,
        processWechatImages: processWechatImages,
        handleImage: handleImage,
        config: config
    };

})();
