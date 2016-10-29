set PKGNAME=gottymng
set LOCALPATH=%~dp0

mklink /J "%GOPATH%\src\%PKGNAME%" "%LOCALPATH%"

go fmt %PKGNAME%

set GOOS=linux
set GOARCH=amd64
go build -gcflags=-trimpath="M:/Joomla" -asmflags=-trimpath="M:/Joomla" -o ../sbin/%PKGNAME%.x86_64 %PKGNAME%

set GOOS=linux
set GOARCH=386
go build -gcflags=-trimpath="M:/Joomla" -asmflags=-trimpath="M:/Joomla" -o ../sbin/%PKGNAME%.i386 %PKGNAME%


rmdir "%GOPATH%\src\%PKGNAME%"