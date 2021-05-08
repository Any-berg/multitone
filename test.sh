# get signal string comprised of characters 0-9, a-d, *, # and _
n=$1
if [[ -z $n ]]; then
  : '
  signals=(
    '1' '2' '3' 'a'
    '4' '5' '6' 'b'
    '7' '8' '9' 'c'
    '*' '0' '#' 'd' )
  '
  signals=( 'D' '1' '2' '3' '4' '5' '6' '7' '8' '9' '0' '*' '#' 'A' 'B' 'C' )

  # list all possible left and right channel signal combinations
  for left in "${signals[@]}"
  do
    for right in "${signals[@]}"
    do
      n+="$left$right"
    done
  done

  # pause
  n+="__"

  # list signals in both channels, when the other channel stays quiet
  other=""
  for one in "${signals[@]}"
  do
    n+="${one}_"
    other+="_$one"
  done
  n+="$other"
fi
# get number of channels (default being 1 for odd number of signals and so on)
ch=${2:-$((2-$((${#n} % 2))))}

php dtmf.php n=$n ch=$ch > test.au
afplay test.au
rm -f test.au
