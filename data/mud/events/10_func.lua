function string:split (s,t)
	local l = {n=0}
	local f = function (s)
		l.n = l.n + 1
		l[l.n] = s
	end
	local p = "%s*(.-)%s*"..t.."%s*"
	s = string.gsub(s,"^%s+","")
	s = string.gsub(s,"%s+$","")
	s = string.gsub(s,p,f)
	l.n = l.n + 1
	l[l.n] = string.gsub(s,"(%s%s*)$","")
	return l
end

function string:join(t, glue)
	local r = ''
	for _,v in pairs(t) do
		r = r..v
	end
	
	return r
end

function table_keys (t)
  local keys = {}
  for k,v in pairs (t) do  append (keys, k)  end
  return keys
  end

function in_table ( e, t )
	for _,v in pairs(t) do
		if (v==e) then
			return true
		end
	end
	return false
end
