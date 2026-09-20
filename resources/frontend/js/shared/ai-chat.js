// Floating "Sun Stock AI" chat. Everything is wired with addEventListener: this file is an ES module (Vite), so functions
// declared in it are NOT global and inline onclick="..." attributes in the Blade view could not reach them — which is why
// every button in the popup was dead.
import { formatAiText } from './ai-text.js';

const $ = (id) => document.getElementById(id);
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const TEXT = {
    vi: { thinking: 'AI đang phân tích...', timeout: 'Yêu cầu quá thời gian, vui lòng thử lại!', error: 'Có lỗi xảy ra, vui lòng thử lại!', hello: 'Xin chào! Tôi là Sun Stock AI.<br>Hỏi tôi bất cứ điều gì về thị trường chứng khoán!' },
    en: { thinking: 'AI is thinking...', timeout: 'The request timed out, please try again!', error: 'Something went wrong, please try again!', hello: 'Hello! I am Sun Stock AI.<br>Ask me anything about the stock market!' },
};

export function initAiChat() {
    const bubble = $('aiChatBubble');
    const popup = $('aiChatPopup');
    const openBtn = $('aiChatOpenBtn');
    const box = $('aiChatMessages');
    const input = $('aiChatInput');
    const sendBtn = $('aiChatSend');
    const lang = $('aiLangSelect');
    if (!bubble || !popup || !openBtn || !box || !input) return;

    let busy = false;

    const t = () => TEXT[lang.value] || TEXT.vi;
    const scrollDown = () => { box.scrollTop = box.scrollHeight; };
    const isOpen = () => popup.style.display !== 'none';

    function setOpen(open) {
        popup.style.display = open ? 'block' : 'none';
        openBtn.setAttribute('aria-expanded', String(open));
        if (open) setTimeout(() => input.focus(), 50);
    }

    function addUser(text) {
        const row = document.createElement('div');
        row.className = 'ai-msg ai-msg-user';
        const span = document.createElement('span');
        span.textContent = text; // textContent: user text never becomes markup
        row.appendChild(span);
        box.appendChild(row);
    }

    function addBot(html, error = false) {
        const row = document.createElement('div');
        row.className = 'ai-msg ai-msg-bot' + (error ? ' ai-msg-error' : '');
        row.innerHTML = '<div class="ai-avatar"><i class="bi bi-' + (error ? 'exclamation-triangle' : 'robot') + '"></i></div><div class="ai-bubble">' + html + '</div>';
        box.appendChild(row);
        return row;
    }

    function addLoading() {
        const row = document.createElement('div');
        row.className = 'ai-msg ai-msg-bot';
        row.innerHTML = '<div class="ai-avatar"><i class="bi bi-robot"></i></div><div class="ai-bubble ai-typing"><span></span><span></span><span></span><em>' + t().thinking + '</em></div>';
        box.appendChild(row);
        return row;
    }

    function setBusy(state) {
        busy = state;
        input.disabled = state;
        if (sendBtn) sendBtn.disabled = state;
    }

    async function send() {
        const msg = input.value.trim();
        if (busy || !msg) return;

        setBusy(true);
        addUser(msg);
        input.value = '';
        const loading = addLoading();
        scrollDown();

        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 60000);

        try {
            const res = await fetch('/ai-chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({ message: msg, lang: lang.value }),
                signal: controller.signal,
            });
            const data = await res.json().catch(() => ({}));
            loading.remove();

            if (!res.ok || !data.answer) {
                addBot(formatAiText(data.message || (res.status === 419 ? 'Phiên đã hết hạn, vui lòng tải lại trang.' : t().error)), true);
            } else {
                addBot(formatAiText(data.answer));
            }
        } catch (err) {
            loading.remove();
            addBot(formatAiText(err.name === 'AbortError' ? t().timeout : t().error), true);
        } finally {
            clearTimeout(timer);
            setBusy(false);
            input.focus();
            scrollDown();
        }
    }

    function clear() {
        box.innerHTML = '<div class="ai-hello"><i class="bi bi-robot"></i>' + t().hello + '</div>';
    }

    openBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        setOpen(!isOpen());
    });
    $('aiChatClose')?.addEventListener('click', () => setOpen(false));
    $('aiChatClear')?.addEventListener('click', clear);
    sendBtn?.addEventListener('click', send);

    // Suggested questions: data-ai-question on each chip
    popup.querySelectorAll('[data-ai-question]').forEach((chip) => {
        chip.addEventListener('click', () => {
            input.value = chip.dataset.aiQuestion;
            input.focus();
        });
    });

    // keydown (not keypress) and not while an IME is composing — Vietnamese Telex/VNI confirm a syllable with Enter too
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing && e.keyCode !== 229) {
            e.preventDefault();
            send();
        }
    });

    lang.addEventListener('change', () => {
        $('aiFlagIcon').innerHTML = '<img src="https://flagcdn.com/24x18/' + (lang.value === 'vi' ? 'vn' : 'us') + '.png" alt="" style="width:26px;height:20px;border-radius:4px;">';
    });

    // Click outside closes the popup. Ignore clicks on nodes that were removed from the DOM meanwhile (e.g. the "clear" button
    // re-rendering the messages): they are no longer inside the bubble but were not outside clicks.
    document.addEventListener('click', (e) => {
        if (isOpen() && e.target.isConnected && !bubble.contains(e.target)) setOpen(false);
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && isOpen()) setOpen(false);
    });
}
