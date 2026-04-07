#Requires AutoHotkey v2.0
#SingleInstance Force
Persistent

A_IconTip := "AutoKey MVP — Alt+Space"
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

    src := A_Clipboard
    if (src = '') {
        A_Clipboard := oldClip
        return
    }

    fixed := ConvertLayout(src)
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

ConvertLayout(text) {
    cyr := CountMatches(text, "[А-Яа-яЁё]")
    lat := CountMatches(text, "[A-Za-z]")
    if (cyr > lat)
        return RuToEn(text)
    return EnToRu(text)
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
