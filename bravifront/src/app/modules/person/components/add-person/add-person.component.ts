import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { MessageService } from 'primeng/api';
import { DialogService, DynamicDialogConfig, DynamicDialogRef } from 'primeng/dynamicdialog';
import { take } from 'rxjs';
import { PersonService } from 'src/app/core/services/person.service';

@Component({
  selector: 'app-add-person',
  templateUrl: './add-person.component.html',
  styleUrls: ['./add-person.component.scss']
})
export class AddPersonComponent implements OnInit{
  form: FormGroup = this.fb.group({
    name: ['', Validators.required],
    lastname: [''],
  });;
  item: any = [];

  constructor(
    private fb: FormBuilder,
    private servicePerson: PersonService, 
    public dialogService: DialogService,
    public dynamicDialogRef: DynamicDialogRef,
    private dialogConfig: DynamicDialogConfig,
    private messageService: MessageService,
    
    ){

  }
  ngOnInit(): void {
    if (this.dialogConfig.data) {
      if (this.dialogConfig.data.item) {
        this.item = this.dialogConfig.data.item;

        this.form = this.fb.group({
          name: [this.item.name, Validators.required],
          lastname: [this.item.lastname],
        });;
        console.log(this.item)
      }
  }
  }

  
  save(){
    if (!!this.item) this.update();
      else this.create();  
  }

  create(){
    this.servicePerson
      .create(this.form.value)
      .pipe(take(1))
      .subscribe(
        (resp: any) => {
          if(resp.success){
            this.messageService.add({severity:'success', summary:'Sucesso', detail:'Pessoa adicionada com sucesso', life: 3000});
            this.dynamicDialogRef.close(true);
          }
        },
        (err) => {
          // this.isLoading = false;
        }
      );
  }

  update(){
    let payload = this.form.value;
    payload.id = this.item.id;

    this.servicePerson
      .update(payload)
      .pipe(take(1))
      .subscribe(
        (resp: any) => {
          if(resp.success){
            this.messageService.add({severity:'success', summary:'Sucesso', detail:'Pessoa editada com sucesso', life: 3000});
            this.dynamicDialogRef.close(true);
          }
        },
        (err) => {
          this.messageService.add({severity:'erro', summary:'Erro', detail:'Houve algum problema!!', life: 2000});
        }
      );
  }

}