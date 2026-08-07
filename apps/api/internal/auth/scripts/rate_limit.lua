local minute_count = tonumber(redis.call("GET", KEYS[1]) or "0")
local day_count = tonumber(redis.call("GET", KEYS[2]) or "0")
if minute_count >= tonumber(ARGV[1]) or day_count >= tonumber(ARGV[2]) then
	return 0
end
minute_count = redis.call("INCR", KEYS[1])
if minute_count == 1 then
	redis.call("EXPIRE", KEYS[1], tonumber(ARGV[3]))
end
day_count = redis.call("INCR", KEYS[2])
if day_count == 1 then
	redis.call("EXPIRE", KEYS[2], tonumber(ARGV[4]))
end
return 1
