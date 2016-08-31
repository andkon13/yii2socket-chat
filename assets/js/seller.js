/**
 * Created by andkon on 19.07.16.
 */


var chat = {
    container: null,
    list: null,
    mess: null,
    socket: null,
    chatId: null,
    currentChat: null,
    tabTemplate: '',
    isTyping: false,
    open: function () {
        if (typeof clientChat != 'undefined') {
            this.chatId = clientChat.chatId;
            this.container = $('#container_' + this.chatId);
            this.list = $('.list', $(this.container));
            this.mess = $('.message', $(this.container));
            $(this.container).on('click', '.messSend', function () {
                chat.send();
            }).on('keyup', 'textarea', function (event) {
                if (event.keyCode == 13) {
                    chat.send();
                    chat.isTyping = false;
                } else if (!chat.isTyping) {
                    chat.socket.send(JSON.stringify({chatId: chat.chatId, message: '', event: 'typing'}));
                    chat.isTyping = true;
                }
            }).on('focusout', 'textarea', function () {
                if (chat.isTyping) {
                    chat.socket.send(JSON.stringify({chatId: chat.chatId, message: '', event: 'typingOff'}));
                    chat.isTyping = false;
                }
            });
            $(this.container).show(1);
            this.socket = new WebSocket("ws://" + clientChat.url);

            this.socket.onopen = function () {
                console.log("connected");
                chat.setList('');
            };

            this.socket.onclose = function (event) {
                if (event.wasClean) {
                    console.log('Соединение закрыто чисто');
                } else {
                    console.log('Обрыв соединения'); // например, "убит" процесс сервера
                }

                if (event.code == 1006) {
                    chat.setList('Соединение с сервером...');
                    setTimeout('chat.open()', 3000);
                    return;
                }
                console.log('Код: ' + event.code + ' причина: ' + event.reason);
                chat.echo('Соединение закрыто');
            };

            this.socket.onmessage = function (event) {
                console.log("Получены данные " + event.data);
                var data = JSON.parse(event.data);
                var messages = data.messages;
                var tpl = clientChat.messageTemplate.current || '';
                var html = [];
                var users = [];
                for (i in messages) {
                    if (typeof html[messages[i].room] == 'undefined') {
                        html[messages[i].room] = '';
                        users[messages[i].room] = (messages[i].user_name != '') ? messages[i].user_name : 'Не авторизованный пользователь';
                    }
                    html[messages[i].room] += tpl
                        .replace(/{message}/g, messages[i].text)
                        .replace(/{date}/g, messages[i].date);
                }

                for (room in html) {
                    chat.echo(html[room], room, users[room]);
                }
            };

            this.socket.onerror = function (error) {
                console.log("Ошибка " + error.message);
                chat.setList("Ошибка " + error.message);
            };
        }
    },
    echo: function (mess, room, name) {
        if (!$(this.list).find("#" + room).length) {
            var tab = this.tabTemplate;
            tab = tab
                .replace(/{room}/g, room)
                .replace(/{name}/g, name);
            $(this.list).append(tab);
            $(this.container).find('.response').show(0);
        }
        this.currentChat = room;
        $(this.list).find("#" + room).append(mess);
    },
    setList: function (html) {
        $(this.list).html(html);
    },
    send: function () {
        var mess = $(this.mess).val();
        $(this.mess).val('');
        this.socket.send(JSON.stringify({chatId: this.chatId, message: mess, room: this.currentChat}));
        return false;
    },
    setCurrentChat: function (id) {
        this.currentChat = id;
        $(".chat > div").hide(0);
        $("#" + id).show(1);
    }
}