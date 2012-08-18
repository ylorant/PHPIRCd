function cmdStatus(user, irc, cmd)
	message(user.id, 'Argent: '..user.money..' PO')
	objlist = table.concat(user.objects, ', ')
	
	if string.len(objlist) == 0 then
		objlist = 'Aucun'
	end
	
	showMoney(user, irc)
	
	message(user.id, 'Objets: '..objlist)
	if in_table('magicbook', user.objects) then
		message(user.id, 'Vous avez le grimoire magique')
	end
end

function cmdJackpot(user, irc)
	rand = math.random(0, 10)
	if rand == 0 then
		addMoney(user.id, 1000)
		message(user.id, 'Bravo, tu as gagné le gros lot !')
	else
		message(user.id, 'Quel dommage, tu as perdu !')
	end
end

function cmdChat(user, irc, cmd)
	if cmd[1] == 'global' then
		joinChannel(user.id, '#TinyMUD')
	else
		joinChannel(user.id, '#'..user.room)
		chatRooms[user.id] = user.room
	end
end

function cmdGo(user, irc, cmd)
	if type(chatRooms[user.id]) ~= 'nil' then
		room = chatRooms[user.id]
		partChannel(user.id, '#'..room)
		chatRooms[user.id] = nil
	end
end
