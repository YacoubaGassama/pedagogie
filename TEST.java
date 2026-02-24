class A{
    protected int x;
    final int y = 10; int z = y; 
    int w = 1;
}
class B{
    void test(){
        System.out.println(new A().x);
    }
    
}
public class TEST {
    public static void main(String[] args) {
        int a = 5;
        int b = a;
        b++;
        System.out.println("a: " + a); // a: 5
        // A a = new A();
        // B b = new B();
        // b.test();
        // System.out.println(a.x);
                // System.out.println(new A().y);

    }
}

class CompteBancaire{
    private String titulaire;
    private double solde;
    public CompteBancaire(String titulaire, double solde) {
        this.titulaire = titulaire;
        this.solde = solde;
    }
    public Double getSolde() {
        return solde;
    }
    public void deposer(double montant) {
        solde += montant;
    }
    public void retirer(double montant) {
        if (montant <= solde) {
            solde -= montant;
        } else {
            System.out.println("Fonds insuffisants");
        }
    }
    public static void main(String[] args) {
        CompteBancaire compte = new CompteBancaire("Alice", 10000.0);
        compte.deposer(2500.0);
        compte.retirer(4000.0);
        System.out.println("Le solde final est de : " + compte.getSolde());
    }
}

interface Affichable {
    String afficher();
}
class Produit implements Affichable {
    private String nom;
    private double prix;
    public Produit(String nom, double prix) {
        this.nom = nom;
        this.prix = prix;
    }
    @Override
    public String afficher() {
        return "Produit: " + nom + ", Prix: " + prix;
    }
    public static void main(String[] args) {
        Produit produit = new Produit("Laptop", 999.99);
        System.out.println(produit.afficher());
    }
}