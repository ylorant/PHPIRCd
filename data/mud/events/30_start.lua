function oldGuyCall(user, irc)
	message(user.id, 'Oh, hai.')
end

function setInitMoney(user, irc)
	setMoney(user.id, 300)
end

function showMoney(user, irc)
	message(user.id, 'Tu as '..user.money..' pièces d\'or.')
	message(user.id, 'Tu es riche ! Enfin... Pour un kosovar quoi.')
end
