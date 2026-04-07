#Requires AutoHotkey v2.0
#SingleInstance Force
Persistent

A_IconTip := "AutoKey v2 — smart layout fix"
TraySetIcon("E:\websmode\autokey\fonts.ico")
A_TrayMenu.Delete()
A_TrayMenu.Add("Open script", (*) => Run(A_ScriptFullPath))
A_TrayMenu.Add("Reload", (*) => Reload())
A_TrayMenu.Add("Exit", (*) => ExitApp())

!Space::FixSelectedText()

FixSelectedText() {
    oldClip := A_Clipboard
    A_Clipboard := ''

    Send '^c'
    if !ClipWait(0.7) {
        A_Clipboard := oldClip
        return
    }

    src := Trim(A_Clipboard)
    if (src = '') {
        A_Clipboard := oldClip
        return
    }

    fixed := SmartConvert(src)
    if (fixed = src) {
        A_Clipboard := oldClip
        return
    }

    A_Clipboard := fixed
    Send '^v'
    Sleep 80

    if IsMostlyCyrillic(fixed) {
        SetLayoutRussian()
    } else {
        SetLayoutEnglish()
    }

    A_Clipboard := oldClip
}

SmartConvert(text) {
    cyr := CountMatches(text, "[А-Яа-яЁё]")
    lat := CountMatches(text, "[A-Za-z]")

    ; Too mixed? leave it alone.
    if (cyr > 0 && lat > 0 && Abs(cyr - lat) <= 1)
        return text

    candidate := (cyr > lat) ? RuToEn(text) : EnToRu(text)

    if (CandidateLooksBetter(text, candidate))
        return candidate
    return text
}

CandidateLooksBetter(src, candidate) {
    if (candidate = src)
        return false

    ; Prefer conversions that produce a cleaner alphabet ratio.
    srcScore := TextScore(src)
    candScore := TextScore(candidate)

    ; If candidate is clearly worse, reject.
    return candScore >= srcScore
}

TextScore(text) {
    cyr := CountMatches(text, "[А-Яа-яЁё]")
    lat := CountMatches(text, "[A-Za-z]")
    digits := CountMatches(text, "[0-9]")
    other := StrLen(text) - cyr - lat - digits
    score := (cyr + lat) * 10 - other * 3 - digits * 2
    return score
}

IsMostlyCyrillic(text) {
    return CountMatches(text, "[А-Яа-яЁё]") > CountMatches(text, "[A-Za-z]")
}

CountMatches(text, pattern) {
    count := 0
    pos := 1
    while pos := RegExMatch(text, pattern, &m, pos) {
        count += 1
        pos += StrLen(m[0])
    }
    return count
}

SetLayoutRussian() {
    PostMessage 0x50, 0, 0x4190419,, "A"
}

SetLayoutEnglish() {
    PostMessage 0x50, 0, 0x4090409,, "A"
}

EnToRu(text) {
    layoutMap := Map()
    layoutMap['q'] := 'й', layoutMap['w'] := 'ц', layoutMap['e'] := 'у', layoutMap['r'] := 'к', layoutMap['t'] := 'е', layoutMap['y'] := 'н'
    layoutMap['u'] := 'г', layoutMap['i'] := 'ш', layoutMap['o'] := 'щ', layoutMap['p'] := 'з'
    layoutMap['['] := 'х', layoutMap[']'] := 'ъ', layoutMap['a'] := 'ф', layoutMap['s'] := 'ы', layoutMap['d'] := 'в', layoutMap['f'] := 'а'
    layoutMap['g'] := 'п', layoutMap['h'] := 'р', layoutMap['j'] := 'о', layoutMap['k'] := 'л', layoutMap['l'] := 'д'
    layoutMap[';'] := 'ж', layoutMap['z'] := 'я', layoutMap['x'] := 'ч', layoutMap['c'] := 'с', layoutMap['v'] := 'м'
    layoutMap['b'] := 'и', layoutMap['n'] := 'т', layoutMap['m'] := 'ь', layoutMap[','] := 'б', layoutMap['.'] := 'ю', layoutMap['/'] := '.'
    return TranslateByMap(text, layoutMap)
}

RuToEn(text) {
    layoutMap := Map()
    layoutMap['й'] := 'q', layoutMap['ц'] := 'w', layoutMap['у'] := 'e', layoutMap['к'] := 'r', layoutMap['е'] := 't', layoutMap['н'] := 'y'
    layoutMap['г'] := 'u', layoutMap['ш'] := 'i', layoutMap['щ'] := 'o', layoutMap['з'] := 'p'
    layoutMap['х'] := '[', layoutMap['ъ'] := ']', layoutMap['ф'] := 'a', layoutMap['ы'] := 's', layoutMap['в'] := 'd', layoutMap['а'] := 'f'
    layoutMap['п'] := 'g', layoutMap['р'] := 'h', layoutMap['о'] := 'j', layoutMap['л'] := 'k', layoutMap['д'] := 'l'
    layoutMap['ж'] := ';', layoutMap['я'] := 'z', layoutMap['ч'] := 'x', layoutMap['с'] := 'c', layoutMap['м'] := 'v'
    layoutMap['и'] := 'b', layoutMap['т'] := 'n', layoutMap['ь'] := 'm', layoutMap['б'] := ',', layoutMap['ю'] := '.'
    return TranslateByMap(text, layoutMap)
}

TranslateByMap(text, layoutMap) {
    out := ''
    for , ch in StrSplit(text) {
        low := StrLower(ch)
        if layoutMap.Has(low) {
            repl := layoutMap[low]
            if (ch ~= '[A-ZА-ЯЁ]')
                repl := StrUpper(repl)
            out .= repl
        } else {
            out .= ch
        }
    }
    return out
}
