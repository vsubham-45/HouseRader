(function () {
  const toggleBtn = document.getElementById('hr-chat-toggle');
  const chatBox = document.getElementById('hr-chat-box');
  const closeBtn = document.getElementById('hr-chat-close');
  const messages = document.getElementById('hr-chat-messages');

  toggleBtn.onclick = () => {
  chatBox.classList.remove('hidden');
  toggleBtn.style.display = 'none';
};
  closeBtn.onclick = () => {
  chatBox.classList.add('hidden');
  toggleBtn.style.display = 'block';
};

  function addMessage(text, type = 'bot') {
    const div = document.createElement('div');
    div.className = type === 'user' ? 'user-msg' : 'bot-msg';
    div.innerHTML = text;
    messages.appendChild(div);
    messages.scrollTop = messages.scrollHeight;
  }

  function botReply(msg) {
    msg = msg.toLowerCase();

    if (msg.includes('buy')) {
      return "You can browse properties using the search bar or filters. Click any listing to see full details.";
    }

    if (msg.includes('sell')) {
      return "To sell a property, login as a seller and add your property from the dashboard.";
    }

    if (msg.includes('featured')) {
      return "Featured listings appear at the top. Sellers can promote properties using paid plans.";
    }

    if (msg.includes('price') || msg.includes('cost')) {
      return "Property prices vary by location and type. Use filters to find what fits your budget.";
    }

    if (msg.includes('contact') || msg.includes('support')) {
      return "You can contact us at 📧 vssubham4545@gmail.com or 📞 123456789.";
    }

    if (msg.includes('login') || msg.includes('signup')) {
      return "Click Login or Sign Up on the top right to get started.";
    }

    return "Sorry, I didn’t understand that. Try asking about buying, selling, featured listings or support.";
  }

  document.querySelectorAll('.quick-actions button').forEach(btn => {
    btn.onclick = () => {
      const msg = btn.dataset.msg;
      addMessage(msg, 'user');
      addMessage(botReply(msg), 'bot');
    };
  });
})();
