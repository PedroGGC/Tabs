/**
 * votes.js — Upvote / Downvote handler
 *
 * Reads CSRF token from the <meta name="_csrf"> tag (added by headTags()).
 * Uses event delegation on document so it works with any page that has
 * data-vote buttons, without touching transitions.js.
 */
(function () {
    'use strict';

    const CSRF_META = document.querySelector('meta[name="_csrf"]');
    const csrfToken = CSRF_META ? CSRF_META.content : '';

    function applyVoteState(group, score, userVote) {
        const counter = group.querySelector('.vote-count');
        const upBtn   = group.querySelector('[data-vote="1"]');
        const downBtn = group.querySelector('[data-vote="-1"]');

        if (counter) counter.textContent = score;
        if (upBtn)   upBtn.classList.toggle('active-up',   userVote === 1);
        if (downBtn) downBtn.classList.toggle('active-down', userVote === -1);
    }

    async function handleVote(btn) {
        const group  = btn.closest('.vote-group');
        if (!group) return;

        const itemType = group.dataset.itemType;
        const itemId   = Number(group.dataset.itemId);
        const vote     = Number(btn.dataset.vote);

        if (!itemType || !itemId || (vote !== 1 && vote !== -1)) return;

        group.querySelectorAll('[data-vote]').forEach(b => (b.disabled = true));

        try {
            const body = new URLSearchParams({ item_type: itemType, item_id: itemId, vote, _csrf: csrfToken });
            const res  = await fetch('vote.php', { method: 'POST', body });

            if (res.status === 401) {
                window.location.href = 'login.php';
                return;
            }

            if (!res.ok) {
                console.error('Vote error', res.status);
                return;
            }

            const data = await res.json();
            applyVoteState(group, data.score, data.userVote);
        } catch (err) {
            console.error('Vote fetch failed', err);
        } finally {
            group.querySelectorAll('[data-vote]').forEach(b => (b.disabled = false));
        }
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-vote]');
        if (btn) {
            e.preventDefault();
            e.stopPropagation(); 
            handleVote(btn);
        }
    });
})();
